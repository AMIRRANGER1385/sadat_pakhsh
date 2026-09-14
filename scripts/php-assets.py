from pathlib import Path
import shutil
root=Path(__file__).resolve().parents[1]
target=root/'php-store/public_html'
for folder in ['products','fonts']:
 shutil.copytree(root/'public'/folder,target/folder,dirs_exist_ok=True)
shutil.copy2(root/'app/icon.svg',target/'assets/icon.svg')
shutil.copy2(root/'public/social-cover.png',target/'assets/social-cover.png')
css=(root/'app/globals.css').read_text(encoding='utf8').replace('@import "tailwindcss";','')
css+='''
/* Standalone PHP: explicit base styles, no runtime build tools. */
input,textarea,select{box-sizing:border-box}input[type=checkbox]{width:auto}button{font-family:inherit}h1,h2,h3,h4{margin-bottom:16px}ul{padding:0;list-style:none}
.mini-cart{margin:0}.mini-cart button{width:33px;height:33px;background:#edf2e9;color:#24594c;border-radius:7px;font-size:23px}
.admin-nav a{padding:14px;border-radius:6px;display:block;font-size:12px}.admin-nav a.active{background:var(--green);color:white}
.cart-item .buy-row input{width:70px}.cart-item .buy-row .btn{padding:9px;font-size:10px}.upload-preview{object-fit:contain;background:#fff;border-radius:8px}.agent-cell{max-width:250px;white-space:normal;overflow-wrap:anywhere}
.form-stack label{display:flex;flex-direction:column;gap:9px}.auth-form .btn{width:100%}.detail-grid .buy-row input{width:100px}
.inline-form form{margin:0}.account-panel>.panel{margin-top:25px}.notice{padding:13px;background:#f5f5ea;border-radius:6px}
@media(max-width:760px){.admin-nav a{white-space:nowrap;font-size:10px;padding:10px}.cart-item .buy-row{width:auto}.cart-item .buy-row input{width:60px}}
'''
(target/'assets/shop.css').write_text(css,encoding='utf8')
p=root/'php-store/naylex-app/pages.php';p.write_text(p.read_text(encoding='utf8').replace('</option value="low">','</option>'),encoding='utf8')
print('PHP CSS, font, icons and 8 sample product assets prepared.')
