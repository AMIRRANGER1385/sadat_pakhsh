const reduceMotion=matchMedia('(prefers-reduced-motion: reduce)').matches;
const hero=document.querySelector('[data-hero-motion]');
if(hero&&!reduceMotion&&matchMedia('(pointer:fine)').matches){
 let frame=0;let px=0;let py=0;
 hero.addEventListener('pointermove',event=>{
  const box=hero.getBoundingClientRect();px=(event.clientX-box.left)/box.width-.5;py=(event.clientY-box.top)/box.height-.5;
  if(frame)return;frame=requestAnimationFrame(()=>{frame=0;hero.style.setProperty('--bag-x',`${(px*15).toFixed(1)}px`);hero.style.setProperty('--bag-y',`${(py*12).toFixed(1)}px`);hero.style.setProperty('--bag-r',`${(-2+px*3).toFixed(1)}deg`);hero.style.setProperty('--granule-x',`${(-px*24).toFixed(1)}px`);hero.style.setProperty('--granule-y',`${(-py*20).toFixed(1)}px`);});
 },{passive:true});
 hero.addEventListener('pointerleave',()=>{hero.style.removeProperty('--bag-x');hero.style.removeProperty('--bag-y');hero.style.removeProperty('--bag-r');hero.style.removeProperty('--granule-x');hero.style.removeProperty('--granule-y');});
}
if(!reduceMotion&&'IntersectionObserver'in window){
 document.documentElement.classList.add('motion-ready');
 const observer=new IntersectionObserver(entries=>{for(const entry of entries)if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target);}},{threshold:.12});
 document.querySelectorAll('[data-reveal]').forEach(element=>observer.observe(element));
}else document.querySelectorAll('[data-reveal]').forEach(element=>element.classList.add('is-visible'));

const sizeFinder=document.querySelector('.size-finder');
sizeFinder?.querySelectorAll('[data-size-option]').forEach(button=>button.addEventListener('click',()=>{
 sizeFinder.querySelectorAll('[data-size-option]').forEach(option=>{const active=option===button;option.classList.toggle('is-active',active);option.setAttribute('aria-pressed',String(active));});
 sizeFinder.querySelector('[data-size-name]').textContent=button.dataset.name;
 sizeFinder.querySelector('[data-size-status]').textContent=button.dataset.status;
 sizeFinder.querySelector('[data-size-link-label]').textContent=button.dataset.label;
 sizeFinder.querySelector('[data-size-link]').href=button.dataset.url;
 const image=sizeFinder.querySelector('[data-size-image]');image.src=button.dataset.image;image.alt=button.dataset.name;
 if(!reduceMotion){image.classList.add('is-changing');requestAnimationFrame(()=>requestAnimationFrame(()=>image.classList.remove('is-changing')));}
}));
