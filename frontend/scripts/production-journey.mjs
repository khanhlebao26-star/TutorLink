import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
const origin = process.env.JOURNEY_ORIGIN ?? 'http://localhost:3110';
const api = process.env.JOURNEY_API ?? 'http://localhost:18000';
if (!['localhost','127.0.0.1'].includes(new URL(origin).hostname) || !['localhost','127.0.0.1'].includes(new URL(api).hostname)) throw new Error('This runner only targets an isolated local deployment.');
const output = process.env.JOURNEY_OUTPUT ?? '../outputs/deploy-readiness/before-browser';
await mkdir(output, { recursive: true });
const catalog = await (await fetch(`${api}/api/v1/listings?per_page=1`)).json();
const tutorSlug = catalog.data?.[0]?.tutor_slug;
if (!tutorSlug) throw new Error('Expected public tutor fixture');
const browser = await chromium.launch({channel:'chrome',headless:true});
const samples = [];
try {
  for (const viewport of [{width:390,height:844},{width:1440,height:1000}]) {
    for (let run=1;run<=3;run++) {
      const context = await browser.newContext({viewport,deviceScaleFactor:1});
      const page = await context.newPage();
      await page.addInitScript(() => {
        window.__journeyLcp = 0;
        new PerformanceObserver(list => { window.__journeyLcp = list.getEntries().at(-1)?.startTime ?? 0; }).observe({ type:'largest-contentful-paint', buffered:true });
      });
      const failures=[];
      page.on('pageerror', error => failures.push(error.message));
      for (const [name,path,heading] of [['home','/','Kết nối đúng người'],['marketplace','/marketplace','Tìm người đồng hành'],['tutor',`/tutors/${tutorSlug}`,null]]) {
        const start=performance.now();
        const response=await page.goto(origin+path,{waitUntil:'networkidle'});
        if (response.status()!==200) throw new Error(`${path}: ${response.status()}`);
        if(heading)await page.getByRole('heading',{level:1}).filter({hasText:heading}).waitFor();
        else await page.locator('#tutor-profile-name').waitFor({timeout:5000}).catch(()=>null);
        const readyMs=performance.now()-start;
        const metrics=await page.evaluate(()=>({lcp_ms:window.__journeyLcp,resources:performance.getEntriesByType('resource').map(r=>({name:r.name,transfer_bytes:r.transferSize,encoded_bytes:r.encodedBodySize,type:r.initiatorType})),hero:document.querySelector('.landing-hero-person')?.currentSrc??null}));
        const ready = name !== "tutor" || await page.locator("#tutor-profile-name").isVisible();
        samples.push({viewport,run,page:name,ready,ready_ms:Math.round(readyMs),...metrics});
        if(run===1)await page.screenshot({path:`${output}/${viewport.width}-${name}.png`,fullPage:true});
      }
      if(failures.length)throw new Error(failures.join('\n'));
      await context.close();
    }
  }
  await writeFile(`${output}/journey.json`,JSON.stringify({created_at:new Date().toISOString(),origin,api,sample_count:samples.length,notes:'Cold browser contexts, sequential homepage/marketplace/tutor navigation. Local production build; networkidle contributes to ready_ms. Three samples per viewport are descriptive, not a field p95.',samples},null,2));
  const ready = samples.filter(sample => sample.ready).length;
  console.log(`Saved ${samples.length} page measurements (${ready} ready) to ${output}`);
  if (ready !== samples.length) process.exitCode = 1;
} finally { await browser.close(); }
