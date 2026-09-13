import { test } from 'node:test';
import assert from 'node:assert/strict';
import { gateway,paymentURL } from '../lib/payment';
test('gateway validates authority, verification reference, response codes and sandbox target',async()=>{
 const originalFetch=globalThis.fetch,merchant=process.env.ZARINPAL_MERCHANT_ID,sandbox=process.env.ZARINPAL_SANDBOX;
 process.env.ZARINPAL_MERCHANT_ID='test-only';process.env.ZARINPAL_SANDBOX='true';
 try{
 let reply:unknown={code:100,authority:'A'+'1'.repeat(35)};
 globalThis.fetch=async(input,init)=>{assert.ok(String(input).startsWith('https://sandbox.zarinpal.com/pg/v4/payment/'));const body=JSON.parse(String(init?.body));assert.equal(body.merchant_id,'test-only');assert.equal(body.amount,680000);return Response.json({data:reply})};
 assert.equal((await gateway('request',{amount:680000})).authority,'A'+'1'.repeat(35));
 reply={code:101,authority:'A'+'1'.repeat(35)};await assert.rejects(()=>gateway('request',{amount:680000}));
 reply={code:100,authority:'https://evil.example'};await assert.rejects(()=>gateway('request',{amount:680000}));
 reply={code:100,ref_id:0};await assert.rejects(()=>gateway('verify',{amount:680000}));
 reply={code:101,ref_id:123456};assert.equal((await gateway('verify',{amount:680000})).ref_id,123456);
 assert.ok(paymentURL('A123').startsWith('https://sandbox.zarinpal.com/'));process.env.ZARINPAL_SANDBOX='false';assert.ok(paymentURL('A123').startsWith('https://www.zarinpal.com/'));
 }finally{globalThis.fetch=originalFetch;if(merchant===undefined)delete process.env.ZARINPAL_MERCHANT_ID;else process.env.ZARINPAL_MERCHANT_ID=merchant;if(sandbox===undefined)delete process.env.ZARINPAL_SANDBOX;else process.env.ZARINPAL_SANDBOX=sandbox;}
});
