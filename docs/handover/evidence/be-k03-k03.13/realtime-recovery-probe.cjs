const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const WebSocket = require(path.resolve('frontend/node_modules/next/dist/compiled/ws'));

const out = path.resolve('docs/handover/evidence/be-k03-k03.13');
const origin = 'http://localhost:3000';
const base = 'http://localhost:8000';
const tag = crypto.randomBytes(8).toString('hex');
const report = { started_at: new Date().toISOString(), fixture_tag: tag, cases: [], http: [], service_actions: [] };
const sockets = [];
let fixture;
let chat;
let workerStopped = false;
let reverbStopped = false;

function docker(args, input) {
    return execFileSync('docker', ['compose', ...args], {
        input, encoding: 'utf8', timeout: 60000, stdio: ['pipe', 'pipe', 'pipe'],
    });
}

function php(code) {
    return docker(['exec', '-T', 'backend', 'php'], `<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
if (! app()->environment('local') || config('database.connections.pgsql.database') !== 'tutorlink') {
    throw new \\RuntimeException('Probe requires the named local development database.');
}
${code}`.replaceAll('\\\\', '\\'));
}

class Session {
    constructor(label) { this.label = label; this.cookies = new Map(); }
    async request(method, uri, body, csrf = true) {
        const headers = { Accept: 'application/json', Origin: origin, Referer: `${origin}/` };
        if (this.cookies.size) headers.Cookie = [...this.cookies].map(([k, v]) => `${k}=${v}`).join('; ');
        if (csrf && this.cookies.has('XSRF-TOKEN')) headers['X-XSRF-TOKEN'] = decodeURIComponent(this.cookies.get('XSRF-TOKEN'));
        if (body !== undefined) headers['Content-Type'] = 'application/json';
        const response = await fetch(`${base}${uri}`, {
            method, headers, body: body === undefined ? undefined : JSON.stringify(body),
            redirect: 'manual', signal: AbortSignal.timeout(12000),
        });
        for (const cookie of response.headers.getSetCookie()) {
            const pair = cookie.split(';', 1)[0];
            const at = pair.indexOf('=');
            this.cookies.set(pair.slice(0, at), pair.slice(at + 1));
        }
        const text = await response.text();
        let data;
        try { data = text ? JSON.parse(text) : null; } catch { data = { non_json: true }; }
        report.http.push({ session: this.label, method, path: uri, status: response.status });
        return { status: response.status, body: data, cors_origin: response.headers.get('access-control-allow-origin'), cors_credentials: response.headers.get('access-control-allow-credentials') };
    }
    async login(actor) {
        assert.equal((await this.request('GET', '/sanctum/csrf-cookie')).status, 204);
        const response = await this.request('POST', '/api/v1/auth/login', { email: actor.email, password: fixture.password });
        assert.equal(response.status, 200, `Login ${this.label}`);
        const me = await this.request('GET', '/api/v1/me');
        assert.equal(me.status, 200, `Session ${this.label}`);
        assert.equal(me.body.data.id, actor.id);
    }
}

function socket(label) {
    const ws = new WebSocket(`ws://127.0.0.1:8080/app/${encodeURIComponent(fixture.app_key)}?protocol=7&client=integration-probe&version=1.0`, { origin });
    const state = { label, ws, frames: [], waiters: [] };
    ws.on('message', (bytes) => {
        const frame = JSON.parse(bytes.toString());
        if (typeof frame.data === 'string') {
            try { frame.data = JSON.parse(frame.data); } catch {}
        }
        state.frames.push(frame);
        if (frame.event === 'pusher:ping') ws.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
        for (const waiter of [...state.waiters]) {
            const found = state.frames.slice(waiter.from).find(waiter.predicate);
            if (found) { clearTimeout(waiter.timer); state.waiters.splice(state.waiters.indexOf(waiter), 1); waiter.resolve(found); }
        }
    });
    ws.on('error', (error) => { state.error = error.message; });
    state.wait = (predicate, from = 0, timeout = 15000) => new Promise((resolve, reject) => {
        const found = state.frames.slice(from).find(predicate);
        if (found) return resolve(found);
        const waiter = { predicate, from, resolve };
        waiter.timer = setTimeout(() => {
            state.waiters.splice(state.waiters.indexOf(waiter), 1);
            reject(new Error(`WebSocket timeout: ${label}; ${state.error || 'no matching frame'}`));
        }, timeout);
        state.waiters.push(waiter);
    });
    sockets.push(state);
    return state;
}

function record(number, name, evidence) {
    report.cases.push({ number, name, passed: true, ...evidence });
    console.log(`PASS ${number}: ${name}`);
}

const eventName = 'conversation.messages_changed';
const messagesById = new Map();
function merge(messages) {
    for (const message of messages) messagesById.set(String(message.id), message);
}
function notices(connection) {
    return connection.frames.filter((frame) => frame.event === eventName).length;
}
async function pause(ms) { await new Promise((resolve) => setTimeout(resolve, ms)); }
async function send(session, body) {
    const response = await session.request('POST', `/api/v1/conversations/${chat}/messages`, {
        client_message_id: crypto.randomUUID(), message_type: 'text', body, file_ids: [],
    });
    assert.equal(response.status, 201, `REST send: ${body}`);
    return response.body.data;
}
async function subscribe(session, label) {
    const grant = await session.request('POST', `/api/v1/conversations/${chat}/realtime`, {});
    assert.equal(grant.status, 200, `Grant ${label}`);
    assert.match(grant.body.data.channel, /^conversations\.\d+\.sessions\.[a-f0-9]{64}$/);
    const connection = socket(label);
    const established = await connection.wait((frame) => frame.event === 'pusher:connection_established');
    connection.socketId = established.data.socket_id;
    connection.channel = `private-${grant.body.data.channel}`;
    const auth = await session.request('POST', '/broadcasting/auth', {
        socket_id: connection.socketId, channel_name: connection.channel,
    });
    assert.equal(auth.status, 200, `Auth ${label}`);
    connection.ws.send(JSON.stringify({ event: 'pusher:subscribe', data: {
        channel: connection.channel, auth: auth.body.auth,
    } }));
    await connection.wait((frame) => frame.event === 'pusher_internal:subscription_succeeded' && frame.channel === connection.channel);
    return connection;
}
async function notification(connection, from) {
    const frame = await connection.wait((item) => item.event === eventName, from, 25000);
    assert.deepEqual(frame.data, { conversation_id: chat });
    assert.equal(frame.channel, connection.channel);
    return frame;
}
async function quietAfterControl(connection, count) {
    // A live authorized socket is awaited first; queue completion is checked separately.
    await pause(400);
    assert.equal(notices(connection), count, `Revoked socket ${connection.label} received a business notification`);
}
function ownQueueCount() {
    return Number(php(`
        $count = 0;
        foreach (\\Illuminate\\Support\\Facades\\DB::table('jobs')->where('queue', 'broadcasts')->get(['payload']) as $job) {
            $command = json_decode($job->payload, true)['data']['command'] ?? '';
            if (str_contains($command, 's:14:"conversationId";i:${chat};')) $count++;
        }
        echo $count;
    `));
}
async function queueDrained() {
    const deadline = Date.now() + 20000;
    while (Date.now() < deadline) {
        if (ownQueueCount() === 0) return;
        await pause(150);
    }
    throw new Error('Fixture broadcast queue did not drain.');
}
async function transportFailureObserved() {
    const deadline = Date.now() + 15000;
    while (Date.now() < deadline) {
        const state = JSON.parse(php(`
            $result = null;
            foreach (\\Illuminate\\Support\\Facades\\DB::table('jobs')->where('queue', 'broadcasts')->get() as $job) {
                $command = json_decode($job->payload, true)['data']['command'] ?? '';
                if (str_contains($command, 's:14:"conversationId";i:${chat};') && $job->attempts > 0 && $job->reserved_at === null) {
                    $result = ['attempts' => $job->attempts, 'released_for_retry' => true];
                }
            }
            echo json_encode($result);
        `));
        if (state) return state;
        await pause(200);
    }
    throw new Error('No real failed transport attempt observed while Reverb was down.');
}
function service(action, name) {
    docker([action, name]);
    if (name === 'broadcast-worker') workerStopped = action === 'stop';
    if (name === 'reverb') reverbStopped = action === 'stop';
    report.service_actions.push({ action, service: name, at: new Date().toISOString() });
}
async function resync(session, checkpoint) {
    let cursor = checkpoint;
    let pages = 0;
    const ids = [];
    for (;;) {
        const response = await session.request('GET', `/api/v1/conversations/${chat}/messages?${new URLSearchParams({ after: cursor, per_page: '20' })}`);
        assert.equal(response.status, 200);
        const page = response.body;
        pages++;
        merge(page.data);
        ids.push(...page.data.map((message) => message.id));
        if (page.meta.polling_cursor !== null) cursor = page.meta.polling_cursor;
        if (!page.meta.has_more) return { cursor, pages, ids };
        assert.ok(page.meta.next_cursor);
        cursor = page.meta.next_cursor;
    }
}
async function restoreReverb() {
    service('start', 'reverb');
    const deadline = Date.now() + 15000;
    while (Date.now() < deadline) {
        const alive = php(`echo (int) (bool) @fsockopen('reverb', 8080, $errno, $error, 1);`);
        if (alive.trim() === '1') return;
        await pause(250);
    }
    throw new Error('Reverb did not become reachable after restart.');
}

async function main() {
    fixture = JSON.parse(php(`
        $tag = '${tag}';
        $password = bin2hex(random_bytes(24));
        $actors = \\Illuminate\\Support\\Facades\\DB::transaction(function () use ($tag, $password) {
            $customer = \\Tests\\Support\\TestActors::customer();
            $tutor = \\Tests\\Support\\ProgramApiFixtures::eligibleTutor();
            $outsider = \\Tests\\Support\\TestActors::customer();
            $admin = \\Tests\\Support\\TestActors::admin();
            $result = [];
            foreach (compact('customer', 'tutor', 'outsider', 'admin') as $role => $user) {
                $user->email = "k0313.$role.$tag@example.test";
                $user->full_name = "K0313 integration $tag";
                $user->password_hash = \\Illuminate\\Support\\Facades\\Hash::make($password);
                $user->save();
                $result[$role] = $user->only(['id', 'email']);
            }
            $result['tutor']['profile_id'] = $tutor->tutorProfile->id;
            return $result;
        });
        echo json_encode(['actors' => $actors, 'password' => $password,
            'app_key' => config('broadcasting.connections.reverb.key')], JSON_THROW_ON_ERROR);
    `));
    report.fixture_ids = Object.fromEntries(Object.entries(fixture.actors).map(([role, actor]) => [role, actor.id]));
    const sender = new Session('customer');
    const s1 = new Session('tutor-S1');
    const s2 = new Session('tutor-S2');
    const outsider = new Session('outsider');
    const admin = new Session('admin');
    for (const [session, role] of [[sender, 'customer'], [s1, 'tutor'], [s2, 'tutor'], [outsider, 'outsider'], [admin, 'admin']]) {
        await session.login(fixture.actors[role]);
    }
    const opened = await sender.request('POST', '/api/v1/conversations', { peer_user_id: fixture.actors.tutor.id });
    assert.equal(opened.status, 201);
    chat = Number(opened.body.data.id);
    report.conversation_id = chat;
    const observer = await subscribe(sender, 'customer-observer');
    const first = await subscribe(s1, 'tutor-S1');
    const second = await subscribe(s2, 'tutor-S2');
    assert.notEqual(first.channel, second.channel);
    record(1, 'Two participants and two receiver sessions subscribe to distinct session channels', {
        receiver_sessions: 2, signed_subscriptions: 3, raw_session_ids_or_tokens_logged: false,
    });

    const authPayload = { socket_id: second.socketId, channel_name: first.channel };
    assert.equal((await s2.request('POST', '/broadcasting/auth', authPayload)).status, 403);
    assert.equal((await outsider.request('POST', '/broadcasting/auth', authPayload)).status, 403);
    assert.equal((await admin.request('POST', '/broadcasting/auth', authPayload)).status, 403);
    assert.equal((await s1.request('POST', '/broadcasting/auth', { ...authPayload, socket_id: first.socketId }, false)).status, 419);
    assert.equal((await s1.request('POST', `/api/v1/conversations/${chat}/realtime`, {}, false)).status, 419);
    assert.equal((await outsider.request('POST', `/api/v1/conversations/${chat}/realtime`, {})).status, 404);
    assert.equal((await s1.request('POST', '/broadcasting/auth', {
        socket_id: first.socketId, channel_name: `private-conversations.${chat}`,
    })).status, 422);
    const intruder = socket('outsider');
    const badSocketId = (await intruder.wait((frame) => frame.event === 'pusher:connection_established')).data.socket_id;
    const signature = await s1.request('POST', '/broadcasting/auth', {
        socket_id: first.socketId, channel_name: first.channel,
    });
    intruder.ws.send(JSON.stringify({ event: 'pusher:subscribe', data: {
        channel: first.channel, auth: signature.body.auth,
    } }));
    const rejected = await intruder.wait((frame) => frame.event === 'pusher:error');
    assert.equal(rejected.data.code, 4009);
    record(2, 'Outsider/Admin/cross-session auth and legacy channel are denied; live CSRF and socket-bound signature enforced', {
        authorization_denials: [403, 403, 403], missing_csrf: [419, 419], outsider_grant: 404,
        legacy_channel: 422, wrong_socket_rejected: rejected.data.code, outsider_socket_id_present: Boolean(badSocketId),
    });

    const starts = [observer.frames.length, first.frames.length, second.frames.length];
    const a = await send(sender, 'K0313 initial message');
    await Promise.all([notification(observer, starts[0]), notification(first, starts[1]), notification(second, starts[2])]);
    await queueDrained();
    record(3, 'REST message commits and active session sockets receive only conversation_id', { message_id: a.id, payload_keys: ['conversation_id'] });

    const oldCookies = new Map(s1.cookies);
    const firstCount = notices(first);
    assert.equal((await s1.request('POST', '/api/v1/auth/logout')).status, 204);
    const fromSecond = second.frames.length;
    const b = await send(sender, 'K0313 after S1 logout');
    await notification(second, fromSecond);
    await queueDrained();
    await quietAfterControl(first, firstCount);
    assert.equal(first.ws.readyState, WebSocket.OPEN);
    const stale = new Session('stale-tutor-S1'); stale.cookies = oldCookies;
    const staleGrant = await stale.request('POST', `/api/v1/conversations/${chat}/realtime`, {});
    assert.ok([401, 419].includes(staleGrant.status));
    const staleRead = await stale.request('GET', `/api/v1/conversations/${chat}/messages`);
    assert.equal(staleRead.status, 401);
    record(4, 'Logout S1 leaves its TCP socket open but silent while S2 receives the next notification', {
        logout_status: 204, stale_grant_status: staleGrant.status, stale_cookie_read_status: staleRead.status,
        csrf_note: 'A stale mutation may fail CSRF with 419 before authentication is evaluated.',
        observer_message_id: b.id, old_socket_still_open: true,
    });

    service('stop', 'broadcast-worker');
    const delayed = await send(sender, 'K0313 queued before S2 logout');
    assert.ok(ownQueueCount() > 0);
    const secondCount = notices(second);
    assert.equal((await s2.request('POST', '/api/v1/auth/logout')).status, 204);
    const controlFrom = observer.frames.length;
    service('start', 'broadcast-worker');
    await notification(observer, controlFrom);
    await queueDrained();
    await quietAfterControl(second, secondCount);
    await quietAfterControl(first, firstCount);
    const s3 = new Session('tutor-S3'); await s3.login(fixture.actors.tutor);
    const third = await subscribe(s3, 'tutor-S3');
    assert.notEqual(third.channel, first.channel);
    const initial = await s3.request('GET', `/api/v1/conversations/${chat}/messages`);
    assert.equal(initial.status, 200);
    assert.ok(initial.body.data.some((message) => message.id === delayed.id));
    record(5, 'Queued job rechecks logout at execution; fresh login uses a new channel and REST recovers missed message', { queued_message_id: delayed.id, prior_sockets_silent: true, fresh_channel_changed: true });

    async function suspension(profile, session, connection, number) {
        const path = profile ? `/api/v1/admin/tutor-profiles/${fixture.actors.tutor.profile_id}` : `/api/v1/admin/users/${fixture.actors.tutor.id}`;
        const count = notices(connection);
        const membersBefore = php(`echo json_encode(\\Illuminate\\Support\\Facades\\DB::table('conversation_members')->where('conversation_id', ${chat})->orderBy('user_id')->get());`);
        service('stop', 'broadcast-worker');
        const committed = await send(sender, profile ? 'K0313 before Profile suspension' : 'K0313 before account suspension');
        assert.ok(ownQueueCount() > 0);
        assert.equal((await admin.request('POST', `${path}/suspend`, { reason: `K0313 probe ${tag}` })).status, 200);
        const observerStart = observer.frames.length;
        service('start', 'broadcast-worker');
        await notification(observer, observerStart);
        await queueDrained();
        await quietAfterControl(connection, count);
        assert.equal(connection.ws.readyState, WebSocket.OPEN);
        assert.equal((await session.request('GET', `/api/v1/conversations/${chat}/messages`)).status, 401);
        const deniedSend = await sender.request('POST', `/api/v1/conversations/${chat}/messages`, {
            client_message_id: crypto.randomUUID(), message_type: 'text', body: 'Must be denied while peer suspended', file_ids: [],
        });
        assert.equal(deniedSend.status, 409);
        assert.equal(membersBefore, php(`echo json_encode(\\Illuminate\\Support\\Facades\\DB::table('conversation_members')->where('conversation_id', ${chat})->orderBy('user_id')->get());`));
        if (profile) assert.equal((await admin.request('POST', `${path}/restore`, { reason: `K0313 restore ${tag}` })).status, 200);
        assert.equal((await admin.request('POST', `/api/v1/admin/users/${fixture.actors.tutor.id}/restore`, { reason: `K0313 restore ${tag}` })).status, 200);
        const fresh = new Session(profile ? 'tutor-S5' : 'tutor-S4'); await fresh.login(fixture.actors.tutor);
        assert.equal((await fresh.request('POST', '/broadcasting/auth', { socket_id: connection.socketId, channel_name: connection.channel })).status, 403);
        const freshConnection = await subscribe(fresh, fresh.label);
        assert.notEqual(freshConnection.channel, connection.channel);
        const renewedFrom = freshConnection.frames.length;
        const restored = await send(sender, 'K0313 restored access');
        await notification(freshConnection, renewedFrom);
        await queueDrained();
        await quietAfterControl(connection, count);
        record(number, profile ? 'Real Profile suspension revokes sessions atomically; restore cannot revive old token' : 'Real account suspension revokes sessions; queued message does not notify revoked socket; restore uses new token', {
            suspend_status: 200, suspended_read_status: 401, suspended_send_status: 409,
            old_token_auth_after_restore: 403, queued_message_id: committed.id,
            restored_message_id: restored.id, membership_and_read_cursor_unchanged: true,
        });
        return [fresh, freshConnection];
    }
    const [s4, fourth] = await suspension(false, s3, third, 6);
    let [receiver, live] = await suspension(true, s4, fourth, 7);

    const baseline = await receiver.request('GET', `/api/v1/conversations/${chat}/messages`);
    assert.equal(baseline.status, 200);
    merge(baseline.body.data);
    let cursor = baseline.body.meta.polling_cursor;
    const previousSize = messagesById.size;
    live.ws.terminate();
    const seeded = JSON.parse(php(`
        $sender = \\App\\Models\\User::findOrFail(${fixture.actors.tutor.id});
        $timestamp = now('UTC')->format('Y-m-d H:i:s.uP');
        $ids = [];
        for ($i = 0; $i < 55; $i++) {
            $ids[] = \\Tests\\Support\\MessageSendFixtures::seed(${chat}, $sender, ['created_at' => $timestamp, 'updated_at' => $timestamp]);
        }
        echo json_encode($ids, JSON_THROW_ON_ERROR);
    `));
    live = await subscribe(receiver, 'tutor-reconnected');
    const catchup = await resync(receiver, cursor); cursor = catchup.cursor;
    assert.equal(catchup.pages, 3);
    assert.deepEqual(catchup.ids, seeded);
    assert.equal(messagesById.size, previousSize + 55);
    const beforeRetry = messagesById.size;
    const retryPage = await resync(receiver, baseline.body.meta.polling_cursor);
    assert.equal(messagesById.size, beforeRetry);
    record(8, 'Disconnect/reconnect drains three pages with 55 identical timestamps; repeated resync dedupes server IDs', {
        seeded_fixture_messages: 55, real_rest_pages: catchup.pages, retry_pages: retryPage.pages,
        unique_messages: messagesById.size, fixture_note: '55 messages seeded directly for pagination; not a quota-bypass acceptance claim',
    });

    service('stop', 'broadcast-worker');
    const payload = { client_message_id: crypto.randomUUID(), message_type: 'text', body: 'K0313 worker outage', file_ids: [] };
    const outageSend = await sender.request('POST', `/api/v1/conversations/${chat}/messages`, payload);
    assert.equal(outageSend.status, 201);
    const pendingBeforeReplay = ownQueueCount();
    assert.equal(pendingBeforeReplay, 1);
    const replay = await sender.request('POST', `/api/v1/conversations/${chat}/messages`, payload);
    assert.equal(replay.status, 200); assert.equal(replay.body.data.id, outageSend.body.data.id);
    assert.equal(ownQueueCount(), pendingBeforeReplay);
    const recover = await resync(receiver, cursor); cursor = recover.cursor;
    assert.ok(recover.ids.includes(outageSend.body.data.id));
    const size = messagesById.size; const restartFrom = live.frames.length;
    service('start', 'broadcast-worker');
    await notification(live, restartFrom); await queueDrained();
    cursor = (await resync(receiver, cursor)).cursor;
    assert.equal(messagesById.size, size);
    record(9, 'Worker outage preserves REST send/read/replay/resync; delayed notification does not duplicate a message', { send_status: 201, replay_status: 200, message_id: outageSend.body.data.id, duplicates: 0 });

    service('stop', 'reverb');
    const offline = await send(sender, 'K0313 Reverb outage');
    const offlineRead = await receiver.request('GET', `/api/v1/conversations/${chat}/messages`);
    assert.equal(offlineRead.status, 200);
    const offlineSync = await resync(receiver, cursor); cursor = offlineSync.cursor;
    assert.ok(offlineSync.ids.includes(offline.id));
    assert.equal(live.ws.readyState, WebSocket.CLOSED);
    const failedTransport = await transportFailureObserved();
    await restoreReverb();
    live = await subscribe(receiver, 'tutor-after-Reverb-restart');
    cursor = (await resync(receiver, cursor)).cursor;
    const restoredFrom = live.frames.length;
    const afterRestart = await send(sender, 'K0313 after Reverb restart');
    await notification(live, restoredFrom); await queueDrained();
    record(10, 'Reverb outage leaves REST/read/resync available; restart permits new socket/auth/subscription', {
        outage_message_id: offline.id, outage_send_status: 201, outage_read_status: 200,
        recovered_via_rest: true, new_notification_after_restart: afterRestart.id,
        real_transport_failure: failedTransport,
        delivery_of_outage_notification_required: false,
    });

    cursor = (await resync(receiver, cursor)).cursor;
    let from = live.frames.length;
    const apiFirst = await send(sender, 'K0313 API first consumer');
    const beforeApiFirst = messagesById.size;
    merge([apiFirst]);
    await notification(live, from);
    cursor = (await resync(receiver, cursor)).cursor;
    assert.equal(messagesById.size, beforeApiFirst + 1);
    record(11, 'Consumer merges API response before event-driven resync without duplication', { message_id: apiFirst.id, duplicates: 0, scope: 'probe consumer; no Echo/UI claim' });

    from = live.frames.length;
    const beforeEventFirst = messagesById.size;
    const heldApiResponse = await send(sender, 'K0313 event first consumer');
    await notification(live, from);
    cursor = (await resync(receiver, cursor)).cursor;
    merge([heldApiResponse]);
    assert.equal(messagesById.size, beforeEventFirst + 1);
    record(12, 'Consumer handles event/resync before releasing the buffered API response without duplication', {
        message_id: heldApiResponse.id, duplicates: 0,
        scope: 'controlled consumer processing order; not a claim about network arrival order or UI',
    });
    report.passed = true;
}

async function cleanup() {
    for (const connection of sockets) connection.ws.terminate();
    if (reverbStopped) await restoreReverb();
    if (workerStopped) service('start', 'broadcast-worker');
    if (!fixture) return;
    const data = Buffer.from(JSON.stringify({ ids: Object.values(fixture.actors).map((actor) => actor.id), chat: chat || null, tag })).toString('base64');
    report.cleanup = JSON.parse(php(`
        $probe = json_decode(base64_decode('${data}'), true, 512, JSON_THROW_ON_ERROR);
        $db = \\Illuminate\\Support\\Facades\\DB::class;
        foreach ($db::table('users')->whereIn('id', $probe['ids'])->get() as $user) {
            if (! str_contains($user->email, '.'.$probe['tag'].'@example.test')) throw new \\RuntimeException('Cleanup fixture ownership mismatch.');
        }
        $removed = $db::transaction(function () use ($db, $probe) {
            $jobs = 0;
            foreach (['jobs', 'failed_jobs'] as $table) {
                foreach ($db::table($table)->get(['id', 'payload']) as $job) {
                    $command = json_decode($job->payload, true)['data']['command'] ?? '';
                    if ($probe['chat'] && str_contains($command, 's:14:"conversationId";i:'.$probe['chat'].';')) {
                        $jobs += $db::table($table)->where('id', $job->id)->delete();
                    }
                }
            }
            if ($probe['chat']) {
                $db::table('conversation_members')->where('conversation_id', $probe['chat'])->delete();
                $db::table('messages')->where('conversation_id', $probe['chat'])->delete();
                $db::table('conversations')->where('id', $probe['chat'])->delete();
            }
            $profiles = $db::table('tutor_profiles')->whereIn('user_id', $probe['ids'])->pluck('id');
            $db::table('audit_logs')->whereIn('actor_id', $probe['ids'])->delete();
            $db::table('notifications')->whereIn('user_id', $probe['ids'])->delete();
            $db::table('notification_outbox')->whereIn('user_id', $probe['ids'])->delete();
            $db::table('verification_documents')->whereIn('tutor_profile_id', $profiles)->delete();
            $db::table('files')->whereIn('owner_id', $probe['ids'])->delete();
            $db::table('customer_profiles')->whereIn('user_id', $probe['ids'])->delete();
            $db::table('tutor_profiles')->whereIn('user_id', $probe['ids'])->delete();
            $db::table('sessions')->whereIn('user_id', $probe['ids'])->delete();
            $db::table('realtime_sessions')->whereIn('user_id', $probe['ids'])->delete();
            $count = $db::table('users')->whereIn('id', $probe['ids'])->delete();
            return ['users_removed' => $count,
                'remaining_users' => $db::table('users')->whereIn('id', $probe['ids'])->count(),
                'remaining_conversations' => $probe['chat'] ? $db::table('conversations')->where('id', $probe['chat'])->count() : 0,
                'remaining_messages' => $probe['chat'] ? $db::table('messages')->where('conversation_id', $probe['chat'])->count() : 0,
                'remaining_files' => $db::table('files')->whereIn('owner_id', $probe['ids'])->count(),
                'remaining_sessions' => $db::table('sessions')->whereIn('user_id', $probe['ids'])->count(),
                'remaining_realtime_sessions' => $db::table('realtime_sessions')->whereIn('user_id', $probe['ids'])->count(),
                'probe_jobs_removed' => $jobs];
        });
        echo json_encode($removed, JSON_THROW_ON_ERROR);
    `));
}

(async () => {
    fs.mkdirSync(out, { recursive: true });
    try { await main(); }
    catch (error) { report.passed = false; report.error = error.message; console.error(error.message); }
    finally {
        try { await cleanup(); }
        catch (error) { report.passed = false; report.cleanup_error = error.message; console.error(`Cleanup: ${error.message}`); }
        report.finished_at = new Date().toISOString();
        fs.writeFileSync(path.join(out, 'realtime-recovery.json'), JSON.stringify(report, null, 2) + '\n');
        console.log(`Result: ${report.cases.length}/12; passed=${report.passed}; cleanup=${JSON.stringify(report.cleanup || null)}`);
        process.exitCode = report.passed ? 0 : 1;
    }
})();

