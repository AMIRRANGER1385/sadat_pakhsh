import { test } from 'node:test';
import assert from 'node:assert/strict';
import sharp from 'sharp';
import { optimizeUpload,readUpload,MAX_UPLOAD_BYTES,mediaName } from '../lib/uploads';
test('real image re-encoded, resized and metadata removed',async()=>{const source=await sharp({create:{width:1800,height:900,channels:3,background:'#24594c'}}).jpeg().withMetadata().toBuffer();const result=await optimizeUpload(source);const meta=await sharp(result).metadata();assert.equal(meta.format,'webp');assert.equal(meta.width,1600);assert.equal(meta.height,800);assert.equal(meta.exif,undefined);assert.equal(meta.icc,undefined)});
test('renamed script, SVG and corrupt image rejected',async()=>{for(const data of [Buffer.from('<?php echo 1; ?>'),Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>'),Buffer.from([0xff,0xd8,0xff,1,2,3])])await assert.rejects(()=>optimizeUpload(data))});
test('oversized and unsafe paths rejected',async()=>{await assert.rejects(()=>optimizeUpload(Buffer.alloc(MAX_UPLOAD_BYTES+1)));for(const name of ['../../.env','photo.svg','test.webp','123.webp'])assert.equal(mediaName.test(name),false);assert.equal(mediaName.test('c5f6b1aa-ef15-402d-876a-f61504249303.webp'),true)});
test('streamed body limit enforced without content-length',async()=>{const req=new Request('http://localhost/upload',{method:'POST',body:new Uint8Array(MAX_UPLOAD_BYTES+1)});await assert.rejects(()=>readUpload(req));assert.equal((await readUpload(new Request('http://localhost/upload',{method:'POST',body:'test'}))).toString(),'test')});
