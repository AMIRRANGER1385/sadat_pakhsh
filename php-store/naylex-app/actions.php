<?php
declare(strict_types=1);
function handle_action(): never {
 global $path;
 if($path==='/admin/upload'){$u=admin_user();rate_limit('upload:'.$u['id'],40);$image=upload_image($_FILES['photo']??[]);header('Content-Type: application/json; charset=utf-8');echo json_encode(['url'=>$image]);exit;}
 $action=text_input('action',1,40);
 if(in_array($action,['login','register'],true)){
  rate_limit('auth-global',150);$username=digits(text_input('username',3,100));$_SESSION['login_username']=$username;rate_limit('auth:'.$username,10);
  if($action==='login'){$password=$_POST['password']??'';if(!is_string($password)||strlen($password)>72)throw new ShopError('اطلاعات ورود نامعتبر است.');$u=login_account($username);if($u)rate_limit('auth-account:'.$u['id'],10);$valid=password_verify($password,$u['password']??'$2y$12$R9h/cIPz0gi.URNNX3kh2OPST9/PgBkqquzi.Ss7KIUgO2t0jWMUW');if(!$u||!$valid)throw new ShopError('نام کاربری یا رمز عبور نادرست است.');login_user($u);redirect($u['role']==='ADMIN'?'/admin':'/account');}
  if(!preg_match('/^09\d{9}$/',$username))throw new ShopError('شماره موبایل معتبر وارد کنید.');
  $name=text_input('name',3,120);
  if(one('SELECT id FROM ns_users WHERE username=? OR name=?',[$username,$name]))throw new ShopError('شما قبلاً حساب دارید. از بخش ورود وارد شوید یا بازیابی رمز را انتخاب کنید.');
  $password=password_input('password');
  $wholesale=($_POST['account_type']??'')==='wholesale'?'PENDING':'NONE';
  transaction(function()use($username,$name,$password,$wholesale){query('INSERT INTO ns_users(username,name,password,wholesale_status) VALUES (?,?,?,?)',[$username,$name,password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),$wholesale]);login_user(one('SELECT * FROM ns_users WHERE id=?',[db()->lastInsertId()]),'REGISTER');});redirect('/account');
 }
 if($action==='switch_account'){$destination=$_POST['destination']??'/login';if(!in_array($destination,['/login','/register','/wholesale'],true))$destination='/login';$cart=$_SESSION['cart']??[];$_SESSION=['cart'=>$cart];session_regenerate_id(true);redirect($destination);}
 if($action==='logout'){$_SESSION=[];session_regenerate_id(true);redirect('/');}
 if($action==='password'){$u=current_user();if(!$u)throw new ShopError('ابتدا وارد شوید.',401);rate_limit('password:'.$u['id'],5);$old=$_POST['current_password']??'';if(!is_string($old)||strlen($old)>72||!password_verify($old,$u['password']))throw new ShopError('رمز فعلی نادرست است.');$password=password_input('new_password');query('UPDATE ns_users SET password=?,session_version=session_version+1 WHERE id=?',[password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),$u['id']]);login_user(one('SELECT * FROM ns_users WHERE id=?',[$u['id']]),'PASSWORD_CHANGE');flash('رمز تغییر کرد و نشست‌های قبلی باطل شدند.');redirect('/account');}
 if(in_array($action,['cart_add','cart_set','cart_remove','cart_decrease'],true)){
  $id=number_input('id',1,PHP_INT_MAX);$qty=$action==='cart_remove'?0:number_input('quantity',1,1000);
  $p=one('SELECT * FROM ns_products WHERE id=? AND active=1',[$id]);if(!$p&&$action!=='cart_remove')throw new ShopError('محصول موجود نیست.');
  if($action==='cart_decrease')$qty=max(0,(int)($_SESSION['cart'][$id]??0)-1);
  if($action==='cart_add')$qty+=(int)($_SESSION['cart'][$id]??0);
  if($qty>1000||($action!=='cart_decrease'&&$p&&$qty>(int)$p['stock']))throw new ShopError('موجودی کافی نیست.');
  if($qty)$_SESSION['cart'][$id]=$qty;else unset($_SESSION['cart'][$id]);unset($_SESSION['checkout_key']);if(($_SERVER['HTTP_ACCEPT']??'')==='application/json'){$data=cart_preview_data();if(!session_write_close())throw new ShopError('ذخیره سبد انجام نشد.',503);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}flash('سبد خرید به‌روز شد.');redirect('/cart');
 }
 if($action==='checkout')checkout();
 if($action==='track'){$code=strtoupper(text_input('code',8,40));$phone=phone_input();rate_limit('track-global',150);rate_limit('track:'.$phone,20);$o=one('SELECT id,code,status,total,reference,shipping FROM ns_orders WHERE code=? AND phone=?',[$code,$phone]);if(!$o)throw new ShopError('سفارشی با این مشخصات پیدا نشد.');$_SESSION['tracked_order']=$o['id'];redirect('/track');}
 $admin=admin_user();rate_limit('admin:'.$admin['id'],400);
 switch($action){
 case 'product':
  $id=number_input('id',0,PHP_INT_MAX);$name=text_input('name',3,180);$slug=text_input('slug',1,120);if(!preg_match('/^[a-z0-9-]+$/',$slug))throw new ShopError('شناسه آدرس فقط حروف کوچک انگلیسی، عدد و خط تیره باشد.');
  $description=text_input('description',10,5000);$retail=number_input('retail',1);$wholesale=number_input('wholesale',1);if($wholesale>$retail)throw new ShopError('قیمت عمده نباید بیشتر از خرده باشد.');
  $minimum=number_input('minimum',1,1000);$stock=number_input('stock',0,100000);$unit=text_input('unit',1,30);$category=number_input('category_id',1,PHP_INT_MAX);if(!one('SELECT id FROM ns_categories WHERE id=?',[$category]))throw new ShopError('دسته‌بندی نامعتبر است.');$featured=isset($_POST['featured'])?1:0;
  $image=text_input('image',0,500);if(isset($_FILES['photo'])&&$_FILES['photo']['error']!==UPLOAD_ERR_NO_FILE){rate_limit('upload:'.$admin['id'],40);$image=upload_image($_FILES['photo']);}if(!valid_image($image))throw new ShopError('عکس محصول را انتخاب کنید.');
  if($id){$version=number_input('version',1,PHP_INT_MAX);$changed=query('UPDATE ns_products SET name=?,slug=?,description=?,image=?,retail=?,wholesale=?,minimum=?,stock=?,unit=?,category_id=?,featured=?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=? AND version=?',[$name,$slug,$description,$image,$retail,$wholesale,$minimum,$stock,$unit,$category,$featured,$id,$version])->rowCount();if(!$changed)throw new ShopError('موجودی یا محصول در این فاصله تغییر کرده؛ صفحه ویرایش را دوباره باز کنید.');}
  else query('INSERT INTO ns_products(name,slug,description,image,retail,wholesale,minimum,stock,unit,category_id,featured) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$name,$slug,$description,$image,$retail,$wholesale,$minimum,$stock,$unit,$category,$featured]);
  flash('محصول ذخیره شد.');redirect('/admin?tab=products');
 case 'delete_product':query('UPDATE ns_products SET active=0,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?',[number_input('id',1,PHP_INT_MAX)]);break;
 case 'category':$id=number_input('id',0,PHP_INT_MAX);$name=text_input('name',2,120);if($id)query('UPDATE ns_categories SET name=? WHERE id=?',[$name,$id]);else query('INSERT INTO ns_categories(name) VALUES (?)',[$name]);break;
 case 'delete_category':$id=number_input('id',1,PHP_INT_MAX);if(one('SELECT id FROM ns_products WHERE category_id=? LIMIT 1',[$id]))throw new ShopError('ابتدا محصولات این دسته‌بندی را منتقل کنید.');query('DELETE FROM ns_categories WHERE id=?',[$id]);break;
 case 'customer':$status=text_input('status');if(!in_array($status,['APPROVED','REJECTED'],true))throw new ShopError('وضعیت نامعتبر است.');query("UPDATE ns_users SET wholesale_status=? WHERE id=? AND role='CUSTOMER'",[$status,number_input('id',1,PHP_INT_MAX)]);break;
 case 'shipping':query('UPDATE ns_settings SET shipping=?,free_above=? WHERE id=1',[number_input('shipping'),number_input('free_above')]);break;
 case 'company':
  $phone=digits(text_input('company_phone',0,25));if($phone&&!preg_match('/^\+?[0-9 ()-]{7,25}$/',$phone))throw new ShopError('تلفن شرکت نامعتبر است.');$email=text_input('company_email',0,150);if($email&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new ShopError('ایمیل نامعتبر است.');$postal=digits(text_input('company_postal_code',0,10));if($postal&&!preg_match('/^\d{10}$/',$postal))throw new ShopError('کد پستی باید ۱۰ رقم باشد.');
  query('UPDATE ns_settings SET company_name=?,company_about=?,company_phone=?,company_email=?,company_address=?,company_hours=?,company_postal_code=? WHERE id=1',[text_input('company_name',2,120),text_input('company_about',0,6000),$phone,$email,text_input('company_address',0,1000),text_input('company_hours',0,200),$postal]);break;
 case 'order':
  $id=number_input('id',1,PHP_INT_MAX);$status=text_input('status');
  transaction(function()use($id,$status){$o=one('SELECT * FROM ns_orders WHERE id=? FOR UPDATE',[$id]);if(!$o)throw new ShopError('سفارش یافت نشد.');$allowed=['PAID'=>['SHIPPED'],'SHIPPED'=>['DELIVERED'],'PENDING'=>['CANCELLED']];if(!in_array($status,$allowed[$o['status']]??[],true))throw new ShopError('تغییر وضعیت مجاز نیست.');if($status==='CANCELLED'&&$o['authority'])throw new ShopError('سفارش دارای authority باید ابتدا در درگاه بررسی شود.');query('UPDATE ns_orders SET status=? WHERE id=?',[$status,$id]);if($status==='CANCELLED')foreach(all('SELECT * FROM ns_order_items WHERE order_id=?',[$id])as$i)query('UPDATE ns_products SET stock=stock+?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?',[$i['quantity'],$i['product_id']]);});break;
 case 'verify_order':$id=number_input('id',1,PHP_INT_MAX);verify_order_payment($id);break;
 default:throw new ShopError('عملیات پیدا نشد.',404);
 }
 $tab=['delete_product'=>'products','category'=>'categories','delete_category'=>'categories','customer'=>'customers','shipping'=>'shipping','company'=>'company','order'=>'orders','verify_order'=>'orders'][$action]??'dashboard';flash('تغییرات ذخیره شد.');redirect('/admin?tab='.$tab);
}
