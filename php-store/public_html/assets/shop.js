'use strict';
document.querySelectorAll('.auth-form input[name=password]').forEach(input=>{
 const toggle=document.createElement('button');toggle.type='button';toggle.className='text-link';toggle.textContent='نمایش رمز';toggle.setAttribute('aria-pressed','false');
 toggle.addEventListener('click',()=>{const visible=input.type==='password';input.type=visible?'text':'password';toggle.textContent=visible?'پنهان کردن رمز':'نمایش رمز';toggle.setAttribute('aria-pressed',String(visible));});input.insertAdjacentElement('afterend',toggle);
});
const cartNumber=new Intl.NumberFormat('fa-IR');
function renderCart(data){
 const count=document.querySelector('[data-cart-count]');if(!count)return;
 count.textContent=cartNumber.format(data.count);
 document.querySelector('[data-cart-total]').textContent=cartNumber.format(data.total);
 const list=document.querySelector('[data-cart-items]');list.replaceChildren();
 if(!data.items.length){const empty=document.createElement('p');empty.textContent='سبد خرید شما خالی است.';list.append(empty);}
 data.items.forEach(item=>{
  const row=document.createElement('article');row.className='cart-preview-item';
  const img=document.createElement('img');img.src=item.image;img.alt=item.name;img.width=64;img.height=64;
  const body=document.createElement('div');const title=document.createElement('strong');title.textContent=item.name;
  const desc=document.createElement('p');desc.textContent=item.description;
  const price=document.createElement('p');price.textContent=`${cartNumber.format(item.quantity)} × ${cartNumber.format(item.price)} تومان`;
  body.append(title,desc,price);row.append(img,body);list.append(row);
 });
 document.querySelectorAll('[data-quantity-control]').forEach(form=>{const id=Number(form.querySelector('[name=id]').value);const qty=data.items.find(item=>item.id===id)?.quantity||0;form.dataset.qty=qty;const first=form.querySelector('[data-first-add]');if(first){first.hidden=qty>0;first.disabled=Number(form.dataset.max)===0;form.querySelector('.quantity-stepper').hidden=qty===0;}form.querySelector('[data-product-count]').textContent=cartNumber.format(qty);form.querySelector('[data-minus]').disabled=qty===0;form.querySelector('[data-plus]').disabled=qty>=Number(form.dataset.max);});

}
const initialCart=document.getElementById('cart-initial');if(initialCart)renderCart(JSON.parse(initialCart.textContent));
let cartBusy=false;
document.addEventListener('submit',async event=>{
 const form=event.target;if(!form.matches('[data-quantity-control]'))return;
 event.preventDefault();if(cartBusy||!event.submitter)return;cartBusy=true;
 const body=new FormData(form);body.set('action',event.submitter.value);
 document.querySelectorAll('[data-quantity-control] button').forEach(b=>b.disabled=true);
 const notice=form.querySelector('[data-cart-message]');notice.textContent='';
 form.setAttribute('aria-busy','true');
 try{const response=await fetch(form.action,{method:'POST',body,headers:{Accept:'application/json'},credentials:'same-origin'});const data=await response.json();if(!response.ok)throw new Error(data.error||'تغییر تعداد انجام نشد.');renderCart(data);if(location.pathname==='/cart')location.reload();}
 catch(error){notice.textContent=error.message||'ارتباط قطع شد؛ صفحه را تازه کنید و سبد را بررسی کنید.';}
 finally{cartBusy=false;form.removeAttribute('aria-busy');document.querySelectorAll('[data-quantity-control]').forEach(f=>{const first=f.querySelector('[data-first-add]');if(first)first.disabled=Number(f.dataset.max)===0;f.querySelector('[data-minus]').disabled=Number(f.dataset.qty)===0;f.querySelector('[data-plus]').disabled=Number(f.dataset.qty)>=Number(f.dataset.max);});}
});

const cartWrap=document.querySelector('.cart-preview-wrap');const cartToggle=document.querySelector('.cart-preview-toggle');
cartToggle?.addEventListener('click',()=>{const open=cartWrap.classList.toggle('is-open');cartToggle.setAttribute('aria-expanded',String(open));});
document.addEventListener('keydown',event=>{if(event.key==='Escape'){cartWrap?.classList.remove('is-open');cartToggle?.setAttribute('aria-expanded','false');document.activeElement?.blur();}});
document.addEventListener('click',event=>{if(cartWrap&&!cartWrap.contains(event.target)){cartWrap.classList.remove('is-open');cartToggle.setAttribute('aria-expanded','false');}});
document.querySelector('[data-menu]')?.addEventListener('click',function(){const open=document.querySelector('.nav').classList.toggle('is-open');this.setAttribute('aria-expanded',String(open));});
document.querySelectorAll('[data-confirm]').forEach(button=>button.addEventListener('click',event=>{if(!confirm(button.dataset.confirm))event.preventDefault();}));
document.querySelectorAll('[data-photo]').forEach(input=>{let previewURL;input.addEventListener('change',()=>{const file=input.files[0];if(!file)return;if(file.size>5*1024*1024||!['image/jpeg','image/png','image/webp'].includes(file.type)){alert('عکس JPEG، PNG یا WebP تا ۵ مگابایت انتخاب کنید.');input.value='';return;}if(previewURL)URL.revokeObjectURL(previewURL);previewURL=URL.createObjectURL(file);document.querySelector('[data-preview]').src=previewURL;});});
