const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const WebSocket = require(path.resolve('frontend/node_modules/next/dist/compiled/ws'));

const out = path.resolve('docs/handover/evidence/be-k03-k03.11');
const origin = 'http://localhost:3000';
const base = 'http://localhost:8000';
const tag = crypto.randomBytes(8).toString('hex');
const report = { started_at: new Date().toISOString(), fixture_tag: tag, cases: [], http: [], service_actions: [] };
const sockets = [];
let fixture;
let chat;
let workerStopped = false;

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

async function main() {
    fixture = JSON.parse(php(`
        $tag = '${tag}';
        $password = bin2hex(random_bytes(24));
        $actors = \\Illuminate\\Support\\Facades\\DB::transaction(function () use ($tag, $password) {
            $customer = \\Tests\\Support\\TestActors::customer(['email' => "k0311.customer.$tag@example.test"]);
            $tutor = \\Tests\\Support\\ProgramApiFixtures::eligibleTutor();
            $tutor->email = "k0311.tutor.$tag@example.test";
            $outsider = \\Tests\\Support\\TestActors::customer(['email' => "k0311.outsider.$tag@example.test"]);
            foreach ([$customer, $tutor, $outsider] as $user) {
                $user->full_name = "K0311 integration $tag";
                $user->password_hash = \\Illuminate\\Support\\Facades\\Hash::make($password);
                $user->save();
            }
            return ['customer' => $customer->only(['id', 'email']), 'tutor' => $tutor->only(['id', 'email']), 'outsider' => $outsider->only(['id', 'email'])];
        });
        echo json_encode(['actors' => $actors, 'password' => $password, 'app_key' => config('broadcasting.connections.reverb.key')], JSON_THROW_ON_ERROR);
    `));
    report.fixture_ids = Object.fromEntries(Object.entries(fixture.actors).map(([role, actor]) => [role, actor.id]));
    const customer = new Session('customer');
    const tutor = new Session('tutor');
    const outsider = new Session('outsider');
    for (const [session, actor] of [[customer, fixture.actors.customer], [tutor, fixture.actors.tutor], [outsider, fixture.actors.outsider]]) await session.login(actor);
    const opened = await customer.request('POST', '/api/v1/conversations', { peer_user_id: fixture.actors.tutor.id });
    assert.equal(opened.status, 201, 'Create fixture conversation through REST');
    chat = opened.body.data.id;
    report.conversation_id = chat;
    const messagesUrl = `/api/v1/conversations/${chat}/messages`;
    const channel = `private-conversations.${chat}`;
    const receiver = socket('member');
    const connected = await receiver.wait((f) => f.event === 'pusher:connection_established');
    const socketId = connected.data.socket_id;
    assert.match(socketId, /^\d+\.\d+$/);
    record(1, 'Member receives socket_id from live Reverb', { socket_id: socketId });

    const authPayload = { socket_id: socketId, channel_name: channel };
    const missingCsrf = await tutor.request('POST', '/broadcasting/auth', authPayload, false);
    assert.equal(missingCsrf.status, 419, 'Live auth endpoint must enforce CSRF');
    const auth = await tutor.request('POST', '/broadcasting/auth', authPayload);
    assert.equal(auth.status, 200);
    assert.equal(typeof auth.body.auth, 'string');
    assert.equal(auth.cors_origin, origin);
    assert.equal(auth.cors_credentials, 'true');
    const preflight = await fetch(`${base}/broadcasting/auth`, { method: 'OPTIONS', headers: {
        Origin: origin, 'Access-Control-Request-Method': 'POST',
        'Access-Control-Request-Headers': 'content-type,x-xsrf-token',
    }, signal: AbortSignal.timeout(10000) });
    assert.equal(preflight.status, 204);
    assert.equal(preflight.headers.get('access-control-allow-origin'), origin);
    record(2, 'Real session and CSRF authorize member channel', { missing_csrf_status: missingCsrf.status, authorized_status: auth.status,
        cors_origin: auth.cors_origin, cors_credentials: auth.cors_credentials, preflight_status: preflight.status });

    receiver.ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel, auth: auth.body.auth } }));
    await receiver.wait((f) => f.event === 'pusher_internal:subscription_succeeded' && f.channel === channel);
    record(3, 'Member subscribes to private conversation channel', { channel });

    const normalFrom = receiver.frames.length;
    const sent = await customer.request('POST', messagesUrl, { client_message_id: crypto.randomUUID(), message_type: 'text', body: `K03.11 normal ${tag}`, file_ids: [] });
    assert.equal(sent.status, 201);
    const notification = await receiver.wait((f) => f.event === 'conversation.messages_changed', normalFrom);
    assert.equal(notification.channel, channel);
    assert.deepEqual(notification.data, { conversation_id: chat });
    record(4, 'REST send produces minimal realtime notification', { message_id: sent.body.data.id, event: notification });

    const intruder = socket('outsider');
    const intruderConnected = await intruder.wait((f) => f.event === 'pusher:connection_established');
    const denied = await outsider.request('POST', '/broadcasting/auth', { socket_id: intruderConnected.data.socket_id, channel_name: channel });
    assert.equal(denied.status, 403);
    intruder.ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel, auth: auth.body.auth } }));
    const deniedFrame = await intruder.wait((f) => f.event === 'pusher:error');
    assert.equal(intruder.frames.some((f) => f.event === 'pusher_internal:subscription_succeeded' && f.channel === channel), false);
    record(5, 'Outsider denied HTTP auth and live subscribe with another socket signature', { auth_status: denied.status, websocket_error: deniedFrame.data });

    const historyBefore = await tutor.request('GET', messagesUrl);
    assert.equal(historyBefore.status, 200);
    const cursor = historyBefore.body.meta.polling_cursor;
    assert.equal(typeof cursor, 'string');
    const resumeFrom = receiver.frames.length;
    workerStopped = true;
    docker(['stop', 'broadcast-worker']);
    report.service_actions.push({ action: 'stop broadcast-worker', at: new Date().toISOString() });
    const outagePayload = { client_message_id: crypto.randomUUID(), message_type: 'text', body: `K03.11 worker outage ${tag}`, file_ids: [] };
    const outageSend = await customer.request('POST', messagesUrl, outagePayload);
    assert.equal(outageSend.status, 201);
    const replay = await customer.request('POST', messagesUrl, outagePayload);
    assert.equal(replay.status, 200);
    assert.equal(replay.body.data.id, outageSend.body.data.id);
    const polling = await tutor.request('GET', `${messagesUrl}?${new URLSearchParams({ after: cursor })}`);
    assert.equal(polling.status, 200);
    assert.deepEqual(polling.body.data.map((m) => m.id), [outageSend.body.data.id]);
    assert.equal(receiver.frames.slice(resumeFrom).some((f) => f.event === 'conversation.messages_changed'), false);
    docker(['start', 'broadcast-worker']);
    workerStopped = false;
    report.service_actions.push({ action: 'start broadcast-worker', at: new Date().toISOString() });
    const resumed = await receiver.wait((f) => f.event === 'conversation.messages_changed', resumeFrom, 20000);
    assert.deepEqual(resumed.data, { conversation_id: chat });
    assert.equal(intruder.frames.some((f) => f.event === 'conversation.messages_changed'), false);
    record(6, 'Worker outage preserves REST write/read/polling and replay; notification arrives after restart', {
        send_status: outageSend.status, retry_status: replay.status, message_id: outageSend.body.data.id,
        polling_status: polling.status, polling_ids: polling.body.data.map((m) => m.id), restored_event: resumed,
        outage_target: 'broadcast-worker only; Reverb remained running',
    });
    report.passed = true;
}

async function cleanup() {
    for (const connection of sockets) connection.ws.terminate();
    if (workerStopped) {
        docker(['start', 'broadcast-worker']);
        workerStopped = false;
        report.service_actions.push({ action: 'restore broadcast-worker in finally', at: new Date().toISOString() });
    }
    if (!fixture) return;
    const data = Buffer.from(JSON.stringify({ ids: Object.values(fixture.actors).map((a) => a.id), chat: chat || null, tag })).toString('base64');
    report.cleanup = JSON.parse(php(`
        $probe = json_decode(base64_decode('${data}'), true, 512, JSON_THROW_ON_ERROR);
        $db = \\Illuminate\\Support\\Facades\\DB::class;
        $users = $db::table('users')->whereIn('id', $probe['ids'])->get();
        foreach ($users as $user) {
            if (! str_contains($user->email, '.'.$probe['tag'].'@example.test')) throw new \\RuntimeException('Cleanup fixture ownership mismatch.');
        }
        $removed = $db::transaction(function () use ($db, $probe) {
            $jobs = 0;
            foreach (['jobs', 'failed_jobs'] as $table) {
                foreach ($db::table($table)->get(['id', 'payload']) as $job) {
                    $payload = json_decode($job->payload, true);
                    if ($probe['chat'] && str_contains($payload['data']['command'] ?? '', 's:14:"conversationId";i:'.$probe['chat'].';')) {
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
            $db::table('verification_documents')->whereIn('tutor_profile_id', $profiles)->delete();
            $db::table('files')->whereIn('owner_id', $probe['ids'])->delete();
            $db::table('customer_profiles')->whereIn('user_id', $probe['ids'])->delete();
            $db::table('tutor_profiles')->whereIn('user_id', $probe['ids'])->delete();
            $db::table('sessions')->whereIn('user_id', $probe['ids'])->delete();
            $count = $db::table('users')->whereIn('id', $probe['ids'])->delete();
            return ['users_removed' => $count, 'remaining_users' => $db::table('users')->whereIn('id', $probe['ids'])->count(),
                'remaining_conversations' => $probe['chat'] ? $db::table('conversations')->where('id', $probe['chat'])->count() : 0,
                'remaining_messages' => $probe['chat'] ? $db::table('messages')->where('conversation_id', $probe['chat'])->count() : 0,
                'remaining_files' => $db::table('files')->whereIn('owner_id', $probe['ids'])->count(),
                'remaining_sessions' => $db::table('sessions')->whereIn('user_id', $probe['ids'])->count(), 'probe_jobs_removed' => $jobs];
        });
        echo json_encode($removed, JSON_THROW_ON_ERROR);
    `));
}

(async () => {
    fs.mkdirSync(out, { recursive: true });
    try { await main(); }
    catch (error) { report.passed = false; report.error = error.message; console.error(error.message); }
    finally {
        try { await cleanup(); } catch (error) { report.passed = false; report.cleanup_error = error.message; console.error(`Cleanup: ${error.message}`); }
        report.finished_at = new Date().toISOString();
        fs.writeFileSync(path.join(out, 'realtime-integration.json'), JSON.stringify(report, null, 2) + '\n');
        console.log(`Result: ${report.cases.length}/6; passed=${report.passed}; cleanup=${JSON.stringify(report.cleanup || null)}`);
        process.exitCode = report.passed ? 0 : 1;
    }
})();
