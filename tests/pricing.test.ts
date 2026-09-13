import { test } from 'node:test';
import assert from 'node:assert/strict';
import { unitPrice,shippingCost } from '../lib/pricing';
const p={retail:100000,wholesale:80000,minimum:10};
test('retail below threshold and wholesale exactly at threshold',()=>{assert.equal(unitPrice(p,9),100000);assert.equal(unitPrice(p,10),80000)});
test('approved wholesale account receives price for one unit',()=>{assert.equal(unitPrice(p,1,true),80000);assert.equal(unitPrice(p,1,false),100000)});
test('shipping boundary and disabled threshold',()=>{assert.equal(shippingCost(999999,{shipping:45000,freeAbove:1000000}),45000);assert.equal(shippingCost(1000000,{shipping:45000,freeAbove:1000000}),0);assert.equal(shippingCost(1000000,{shipping:45000,freeAbove:0}),45000);assert.equal(shippingCost(1,{shipping:0,freeAbove:0}),0)});
