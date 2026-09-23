<?php
declare(strict_types=1);
require_once __DIR__.'/articles.php';
function handle_action(): never {
 global $path;
 if($path==='/admin/upload'){$u=admin_user();rate_limit('upload:'.$u['id'],40);$image=upload_image($_FILES['photo']??[]);header('Content-Type: application/json; charset=utf-8');echo json_encode(['url'=>$image]);exit;}
 $action=text_input('action',1,40);
 if(in_array($action,['login','register'],true)){
  rate_limit('auth-global',150);$username=auth_identifier(text_input('username',3,150));$_SESSION['login_username']=$username;rate_limit('auth:'.$username,10);
  if($action==='login'){$password=$_POST['password']??'';if(!is_string($password)||strlen($password)>72)throw new ShopError('اطلاعات ورود نامعتبر است.');$u=login_account($username);if($u)rate_limit('auth-account:'.$u['id'],10);$valid=password_verify($password,$u['password']??'$2y$12$R9h/cIPz0gi.URNNX3kh2OPST9/PgBkqquzi.Ss7KIUgO2t0jWMUW');if(!$u||!$valid)throw new ShopError('نام کاربری یا رمز عبور نادرست است.');if($u['role']==='ADMIN'&&admin_two_factor((int)$u['id']))begin_admin_login($u);$after=is_string($_SESSION['after_login']??null)?$_SESSION['after_login']:'';unset($_SESSION['after_login']);login_user($u);redirect($u['role']==='ADMIN'?'/admin':($after?:'/account'));}
  if(!valid_auth_identifier($username))throw new ShopError('شماره موبایل یا ایمیل معتبر وارد کنید.');
  $name=text_input('name',3,120);
  if(one('SELECT id FROM ns_users WHERE username=? OR name=?',[$username,$name]))throw new ShopError('شما قبلاً حساب دارید. از بخش ورود وارد شوید یا بازیابی رمز را انتخاب کنید.');
  $password=password_input('password');
  transaction(function()use($username,$name,$password){query('INSERT INTO ns_users(username,name,password,wholesale_status) VALUES (?,?,?,?)',[$username,$name,password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),'NONE']);login_user(one('SELECT * FROM ns_users WHERE id=?',[db()->lastInsertId()]),'REGISTER');});redirect('/account');
 }
 if($action==='switch_account'){$destination=$_POST['destination']??'/login';if(!in_array($destination,['/login','/register','/wholesale'],true))$destination='/login';$cart=$_SESSION['cart']??[];$cartUpdated=$_SESSION['cart_updated_at']??time();$_SESSION=['cart'=>$cart,'cart_updated_at'=>$cartUpdated];session_regenerate_id(true);redirect($destination);}
 if($action==='password_reset')password_reset_action();
 if($action==='logout'){$_SESSION=[];session_regenerate_id(true);redirect('/');}
 if($action==='password'){$u=current_user();if(!$u)throw new ShopError('ابتدا وارد شوید.',401);rate_limit('password:'.$u['id'],5);$old=$_POST['current_password']??'';if(!is_string($old)||strlen($old)>72||!password_verify($old,$u['password']))throw new ShopError('رمز فعلی نادرست است.');$password=password_input('new_password');query('UPDATE ns_users SET password=?,session_version=session_version+1 WHERE id=?',[password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),$u['id']]);login_user(one('SELECT * FROM ns_users WHERE id=?',[$u['id']]),'PASSWORD_CHANGE');flash('رمز تغییر کرد و نشست‌های قبلی باطل شدند.');redirect('/account');}
 if($action==='2fa_login'){$id=(int)($_SESSION['pending_2fa_user']??0);if(!$id||($_SESSION['pending_2fa_expires']??0)<time())throw new ShopError('مهلت تأیید ورود پایان یافته است.',401);rate_limit('2fa-login:'.$id,8);$row=admin_two_factor($id);$u=one("SELECT * FROM ns_users WHERE id=? AND role='ADMIN'",[$id]);if(!$row||!$u||!verify_two_factor_value($row,text_input('two_factor_code',6,30)))throw new ShopError('کد ورود دومرحله‌ای نادرست است.');unset($_SESSION['pending_2fa_user'],$_SESSION['pending_2fa_expires']);login_user($u,'LOGIN_2FA');redirect('/admin');}
 if($action==='review'){$u=current_user();if(!$u||$u['role']!=='CUSTOMER')throw new ShopError('برای ثبت نظر وارد حساب مشتری شوید.',401);$id=number_input('id',1,PHP_INT_MAX);if(!verified_buyer((int)$u['id'],$id))throw new ShopError('ثبت نظر فقط برای خریدار تأییدشده این محصول فعال است.',403);$rating=number_input('rating',1,5);$body=text_input('body',10,1500);query('INSERT INTO ns_reviews(product_id,user_id,rating,body,approved) VALUES (?,?,?,?,0) ON DUPLICATE KEY UPDATE rating=VALUES(rating),body=VALUES(body),approved=0,created_at=UTC_TIMESTAMP()',[$id,$u['id'],$rating,$body]);$p=one('SELECT slug FROM ns_products WHERE id=?',[$id])??throw new ShopError('محصول پیدا نشد.',404);flash('نظر شما ثبت شد و پس از بررسی مدیر نمایش داده می‌شود.');redirect('/products/'.$p['slug'].'#reviews');}
 if(in_array($action,['cart_add','cart_set','cart_remove','cart_decrease'],true)){
  $id=number_input('id',1,PHP_INT_MAX);$qty=$action==='cart_remove'?0:number_input($action==='cart_set'&&isset($_POST['requested_quantity'])?'requested_quantity':'quantity',1,1000);
  $p=one('SELECT * FROM ns_products WHERE id=? AND active=1',[$id]);if(!$p&&$action!=='cart_remove')throw new ShopError('محصول موجود نیست.');
  if($action==='cart_decrease')$qty=max(0,(int)($_SESSION['cart'][$id]??0)-1);
  if($action==='cart_add')$qty+=(int)($_SESSION['cart'][$id]??0);
  if($qty>0&&!isset($_SESSION['cart'][$id])&&count($_SESSION['cart']??[])>=100)throw new ShopError('سبد خرید حداکثر ۱۰۰ محصول متفاوت می‌پذیرد.');
  if($qty>1000||($action!=='cart_decrease'&&$p&&$qty>(int)$p['stock']))throw new ShopError('موجودی کافی نیست.');
  if($qty)$_SESSION['cart'][$id]=$qty;else unset($_SESSION['cart'][$id]);$_SESSION['cart_updated_at']=time();unset($_SESSION['checkout_key']);if($action==='cart_add')analytics_event('add_to_cart',$id);if(($_SERVER['HTTP_ACCEPT']??'')==='application/json'){$data=cart_preview_data();if(!session_write_close())throw new ShopError('ذخیره سبد انجام نشد.',503);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}flash('سبد خرید به‌روز شد.');redirect('/cart');
 }
 if($action==='checkout')checkout();
 if($action==='track'){$code=strtoupper(text_input('code',8,40));$phone=phone_input();rate_limit('track-global',150);rate_limit('track:'.$phone,20);$o=one('SELECT id,code,status,total,reference,shipping FROM ns_orders WHERE code=? AND phone=?',[$code,$phone]);if(!$o)throw new ShopError('سفارشی با این مشخصات پیدا نشد.');$_SESSION['tracked_order']=$o['id'];analytics_event('order_tracking',null,(int)$o['id']);redirect('/track');}
 if($action==='support_message')support_message_action();
 $admin=admin_user();rate_limit('admin:'.$admin['id'],400);
 if($action==='2fa_enable'){$password=$_POST['current_password']??'';if(!is_string($password)||!password_verify($password,$admin['password']))throw new ShopError('رمز فعلی نادرست است.');$secret=$_SESSION['totp_setup_secret']??'';if(!is_string($secret)||verify_totp($secret,text_input('two_factor_code',6,12),time())===null)throw new ShopError('کد برنامه Authenticator نادرست است.');$codes=create_recovery_codes();$hashes=array_map(fn($code)=>password_hash($code,PASSWORD_DEFAULT),$codes);query('INSERT INTO ns_admin_2fa(user_id,secret_cipher,recovery_hashes) VALUES (?,?,?) ON DUPLICATE KEY UPDATE secret_cipher=VALUES(secret_cipher),recovery_hashes=VALUES(recovery_hashes),enabled_at=UTC_TIMESTAMP(),last_counter=-1',[$admin['id'],encrypt_two_factor_secret($secret),json_encode($hashes)]);unset($_SESSION['totp_setup_secret']);$_SESSION['two_factor_recovery_codes']=$codes;audit_admin('2FA_ENABLE',(string)$admin['id']);flash('ورود دومرحله‌ای فعال شد. کدهای بازیابی را ذخیره کنید.');redirect('/admin?tab=security');}
 if($action==='2fa_disable'){$password=$_POST['current_password']??'';$row=admin_two_factor((int)$admin['id']);if(!$row||!is_string($password)||!password_verify($password,$admin['password'])||!verify_two_factor_value($row,text_input('two_factor_code',6,30)))throw new ShopError('رمز یا کد دومرحله‌ای نادرست است.');query('DELETE FROM ns_admin_2fa WHERE user_id=?',[$admin['id']]);audit_admin('2FA_DISABLE',(string)$admin['id']);flash('ورود دومرحله‌ای غیرفعال شد.');redirect('/admin?tab=security');}
 if($action==='content'){$key=text_input('content_key',3,120);if(!preg_match('/^[a-z0-9._-]+$/',$key))throw new ShopError('کلید محتوا معتبر نیست.');$label=text_input('content_label',2,180);$body=text_input('body',1,10000);$return=text_input('return_to',1,500);if(!preg_match('#^/(?:$|about$|faq$|terms$|returns$|payment-guide$|wholesale(?:-buying)?$|guides/[a-z0-9-]+$|products/[a-z0-9-]+$)#',$return))$return='/';query('INSERT INTO ns_content_blocks (`key`,label,body) VALUES (?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label),body=VALUES(body)',[$key,$label,$body]);audit_admin('CONTENT_UPDATE',$key);flash('محتوا ذخیره شد.');redirect($return);}
 switch($action){
 case 'product_import_preview':case 'product_import_commit':require_once __DIR__.'/product-import.php';product_import_action($action);
 case 'article':
  ensure_article_schema();$id=number_input('id',0,PHP_INT_MAX);$title=text_input('title',8,180);$slug=text_input('slug',3,120);if(!preg_match('/^[a-z0-9-]+$/',$slug))throw new ShopError('شناسه آدرس فقط حروف کوچک انگلیسی، عدد و خط تیره باشد.');
  if($id&&!one('SELECT id FROM ns_articles WHERE id=?',[$id]))throw new ShopError('مقاله پیدا نشد.',404);
  if(one('SELECT id FROM ns_articles WHERE slug=? AND id<>?',[$slug,$id]))throw new ShopError('این آدرس مقاله قبلاً استفاده شده است.');
  if($id){$oldArticle=one('SELECT slug FROM ns_articles WHERE id=?',[$id]);if($oldArticle['slug']!==$slug)throw new ShopError('آدرس مقاله ذخیره‌شده ثابت است؛ برای حفظ لینک‌ها آن را تغییر ندهید.');}
  $excerpt=text_input('excerpt',20,350);$body=text_input('body',80,50000);$category=number_input('category_id',0,PHP_INT_MAX);if($category&&!one('SELECT id FROM ns_categories WHERE id=?',[$category]))throw new ShopError('دسته‌بندی نامعتبر است.');
  $image=text_input('image',0,500);if(isset($_FILES['photo'])&&$_FILES['photo']['error']!==UPLOAD_ERR_NO_FILE){rate_limit('upload:'.$admin['id'],40);$image=upload_image($_FILES['photo']);}if(!valid_image($image))throw new ShopError('برای مقاله یک تصویر معتبر انتخاب کنید.');$active=isset($_POST['active'])?1:0;
  if($id){query('UPDATE ns_articles SET slug=?,title=?,excerpt=?,body=?,image=?,category_id=?,active=? WHERE id=?',[$slug,$title,$excerpt,$body,$image,$category?:null,$active,$id]);}
  else query('INSERT INTO ns_articles(slug,title,excerpt,body,image,category_id,active,published_at) VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP())',[$slug,$title,$excerpt,$body,$image,$category?:null,$active]);
  flash('مقاله ذخیره شد.');redirect('/admin?tab=articles');
 case 'delete_article':ensure_article_schema();query('DELETE FROM ns_articles WHERE id=?',[number_input('id',1,PHP_INT_MAX)]);break;
 case 'product':
  $id=number_input('id',0,PHP_INT_MAX);$name=text_input('name',3,180);$slug=text_input('slug',1,120);if(!preg_match('/^[a-z0-9-]+$/',$slug))throw new ShopError('شناسه آدرس فقط حروف کوچک انگلیسی، عدد و خط تیره باشد.');
  $oldProduct=$id?one('SELECT slug,retail,wholesale FROM ns_products WHERE id=?',[$id]):null;if($id&&!$oldProduct)throw new ShopError('محصول پیدا نشد.',404);if(one('SELECT id FROM ns_products WHERE slug=? AND id<>?',[$slug,$id]))throw new ShopError('این شناسه آدرس قبلاً استفاده شده است.');
  $description=text_input('description',10,5000);$retail=number_input('retail',1);$wholesale=number_input('wholesale',1);if($wholesale>$retail)throw new ShopError('قیمت عمده نباید بیشتر از خرده باشد.');
  $seoData=['seo_title'=>text_input('seo_title',0,180),'meta_description'=>text_input('meta_description',0,350),'focus_keyword'=>text_input('focus_keyword',0,180),'intro'=>text_input('seo_intro',0,3000)];
  $minimum=number_input('minimum',1,1000);$stock=number_input('stock',0,100000);$unit=text_input('unit',1,30);$category=number_input('category_id',1,PHP_INT_MAX);if(!one('SELECT id FROM ns_categories WHERE id=?',[$category]))throw new ShopError('دسته‌بندی نامعتبر است.');$featured=isset($_POST['featured'])?1:0;
  $image=text_input('image',0,500);if(isset($_FILES['photo'])&&$_FILES['photo']['error']!==UPLOAD_ERR_NO_FILE){rate_limit('upload:'.$admin['id'],40);$image=upload_image($_FILES['photo']);}if(!valid_image($image))throw new ShopError('عکس محصول را انتخاب کنید.');
  $saleType=text_input('sale_type',1,10);if(!in_array($saleType,['retail','wholesale'],true))throw new ShopError('نوع فروش نامعتبر است.');
  $linked=number_input('wholesale_id',0,PHP_INT_MAX);
  if($saleType==='wholesale')$linked=0;
  if($saleType==='retail'&&!$linked)throw new ShopError('برای محصول خرده، محصول عمدهٔ همین کالا را انتخاب کنید. ابتدا محصول عمده را بسازید و سپس آن را به محصول خرده متصل کنید.');
  if($linked&&($linked===$id||!one("SELECT p.id FROM ns_products p JOIN ns_product_sales s ON s.product_id=p.id WHERE p.id=? AND p.active=1 AND s.sale_type='wholesale'",[$linked])))throw new ShopError('یک محصول عمدهٔ فعال و متفاوت انتخاب کنید.');
  if($saleType==='retail'&&$id&&one('SELECT product_id FROM ns_product_sales WHERE wholesale_id=? LIMIT 1',[$id]))throw new ShopError('ابتدا اتصال محصولات خرده به این محصول عمده را بردارید.');
  db()->beginTransaction();
  if($id){$version=number_input('version',1,PHP_INT_MAX);$changed=query('UPDATE ns_products SET name=?,slug=?,description=?,image=?,retail=?,wholesale=?,minimum=?,stock=?,unit=?,category_id=?,featured=?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=? AND version=?',[$name,$slug,$description,$image,$retail,$wholesale,$minimum,$stock,$unit,$category,$featured,$id,$version])->rowCount();if(!$changed)throw new ShopError('موجودی یا محصول در این فاصله تغییر کرده؛ صفحه ویرایش را دوباره باز کنید.');if($oldProduct['slug']!==$slug)remember_slug('product',$oldProduct['slug'],$id);}
  else {query('INSERT INTO ns_products(name,slug,description,image,retail,wholesale,minimum,stock,unit,category_id,featured) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$name,$slug,$description,$image,$retail,$wholesale,$minimum,$stock,$unit,$category,$featured]);$id=(int)db()->lastInsertId();}
  query('INSERT INTO ns_product_sales(product_id,sale_type,wholesale_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE sale_type=VALUES(sale_type),wholesale_id=VALUES(wholesale_id)',[$id,$saleType,$linked?:null]);
  save_product_seo($id,$seoData);
  if(!$oldProduct||(int)$oldProduct['retail']!==$retail||(int)$oldProduct['wholesale']!==$wholesale)mark_product_price_updated($id);
  $names=$_POST['feature_name']??[];$values=$_POST['feature_value']??[];$icons=$_POST['feature_icon']??[];if(!is_array($names)||!is_array($values)||!is_array($icons)||count($names)>20)throw new ShopError('ویژگی‌های محصول نامعتبر است.');$allowedIcons=['dimensions','weight','material','size','thickness','color','count','other'];query('DELETE FROM ns_product_features WHERE product_id=?',[$id]);foreach($names as$i=>$featureName){if(!is_string($featureName)||!is_string($values[$i]??null)||!is_string($icons[$i]??null))throw new ShopError('ویژگی محصول نامعتبر است.');$featureName=trim($featureName);$featureValue=trim($values[$i]);if($featureName===''&&$featureValue==='')continue;if(mb_strlen($featureName)<2||mb_strlen($featureName)>80||mb_strlen($featureValue)<1||mb_strlen($featureValue)>180||!in_array($icons[$i],$allowedIcons,true))throw new ShopError('نام، مقدار یا آیکون ویژگی محصول معتبر نیست.');query('INSERT INTO ns_product_features(product_id,name,value,icon,sort_order) VALUES (?,?,?,?,?)',[$id,$featureName,$featureValue,$icons[$i],$i]);}
  db()->commit();flash('محصول ذخیره شد.');redirect('/admin?tab=products');
 case 'delete_product':query('UPDATE ns_products SET active=0,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?',[number_input('id',1,PHP_INT_MAX)]);break;
 case 'category':$id=number_input('id',0,PHP_INT_MAX);$name=text_input('name',2,120);$slug=text_input('slug',2,120);if(!preg_match('/^[a-z0-9-]+$/',$slug))throw new ShopError('شناسه دسته فقط حروف کوچک انگلیسی، عدد و خط تیره باشد.');$parent=number_input('parent_id',0,PHP_INT_MAX);$sort=number_input('sort_order',0,10000);if($parent&&($parent===$id||!one('SELECT id FROM ns_categories WHERE id=? AND parent_id IS NULL',[$parent])))throw new ShopError('دسته والد نامعتبر است.');if(one('SELECT id FROM ns_categories WHERE (name=? OR slug=?) AND id<>?',[$name,$slug,$id]))throw new ShopError('نام یا شناسه این دسته قبلاً استفاده شده است.');if($id)query('UPDATE ns_categories SET name=?,slug=?,parent_id=?,sort_order=? WHERE id=?',[$name,$slug,$parent?:null,$sort,$id]);else query('INSERT INTO ns_categories(name,slug,parent_id,sort_order) VALUES (?,?,?,?)',[$name,$slug,$parent?:null,$sort]);break;
 case 'delete_category':$id=number_input('id',1,PHP_INT_MAX);if(one('SELECT id FROM ns_products WHERE category_id=? LIMIT 1',[$id]))throw new ShopError('ابتدا محصولات این دسته‌بندی را منتقل کنید.');if(one('SELECT id FROM ns_categories WHERE parent_id=? LIMIT 1',[$id]))throw new ShopError('ابتدا زیردسته‌های این دسته را منتقل یا حذف کنید.');query('DELETE FROM ns_categories WHERE id=?',[$id]);break;
 case 'customer':
  $id=number_input('id',1,PHP_INT_MAX);$customer=one("SELECT * FROM ns_users WHERE id=? AND role='CUSTOMER'",[$id]);if(!$customer)throw new ShopError('مشتری پیدا نشد.',404);
  $name=text_input('name',3,120);$username=auth_identifier(text_input('username',3,150));if(!valid_auth_identifier($username))throw new ShopError('شماره موبایل یا ایمیل مشتری معتبر نیست.');
  if(one('SELECT id FROM ns_users WHERE username=? AND id<>?',[$username,$id]))throw new ShopError('این شماره موبایل یا ایمیل برای حساب دیگری ثبت شده است.');
  $status=text_input('wholesale_status',0,20);if(!in_array($status,['NONE','REQUESTED','APPROVED','REJECTED'],true))$status='NONE';
  $newPassword=trim((string)($_POST['new_password']??''));
  if($newPassword!==''){
   if(mb_strlen($newPassword)<8||strlen($newPassword)>72)throw new ShopError('رمز جدید باید حداقل ۸ کاراکتر باشد.');
   query('UPDATE ns_users SET username=?,name=?,wholesale_status=?,password=?,session_version=session_version+1 WHERE id=?',[$username,$name,$status,password_hash($newPassword,PASSWORD_BCRYPT,['cost'=>12]),$id]);
  }else query('UPDATE ns_users SET username=?,name=?,wholesale_status=? WHERE id=?',[$username,$name,$status,$id]);
  break;
 case 'delete_customer':
  $id=number_input('id',1,PHP_INT_MAX);$customer=one("SELECT id FROM ns_users WHERE id=? AND role='CUSTOMER'",[$id]);if(!$customer)throw new ShopError('مشتری پیدا نشد.',404);
  transaction(function()use($id){query('UPDATE ns_orders SET user_id=NULL WHERE user_id=?',[$id]);query('DELETE FROM ns_users WHERE id=? AND role=\'CUSTOMER\'',[$id]);});
  break;
 case 'shipping':query('UPDATE ns_settings SET shipping=?,free_above=? WHERE id=1',[number_input('shipping'),number_input('free_above')]);break;
 case 'company':
  $phone=digits(text_input('company_phone',0,25));if($phone&&!preg_match('/^\+?[0-9 ()-]{7,25}$/',$phone))throw new ShopError('تلفن شرکت نامعتبر است.');$email=text_input('company_email',0,150);if($email&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new ShopError('ایمیل نامعتبر است.');$postal=digits(text_input('company_postal_code',0,10));if($postal&&!preg_match('/^\d{10}$/',$postal))throw new ShopError('کد پستی باید ۱۰ رقم باشد.');
  $whatsapp=digits(text_input('company_whatsapp',0,15));if($whatsapp&&!preg_match('/^(?:09\d{9}|98\d{10})$/',$whatsapp))throw new ShopError('شماره واتساپ باید با 09 یا 98 و بدون فاصله وارد شود.');query('INSERT INTO ns_content_blocks (`key`,label,body) VALUES (?,?,?) ON DUPLICATE KEY UPDATE body=VALUES(body)', ['company.whatsapp','شماره واتساپ',$whatsapp]);
  query('UPDATE ns_settings SET company_name=?,company_about=?,company_phone=?,company_email=?,company_address=?,company_hours=?,company_postal_code=? WHERE id=1',[text_input('company_name',2,120),text_input('company_about',0,6000),$phone,$email,text_input('company_address',0,1000),text_input('company_hours',0,200),$postal]);break;
 case 'order':
  $id=number_input('id',1,PHP_INT_MAX);$status=text_input('status');
  transaction(function()use($id,$status){$o=one('SELECT * FROM ns_orders WHERE id=? FOR UPDATE',[$id]);if(!$o)throw new ShopError('سفارش یافت نشد.');$allowed=['PAID'=>['SHIPPED'],'SHIPPED'=>['DELIVERED'],'PENDING'=>['CANCELLED']];if(!in_array($status,$allowed[$o['status']]??[],true))throw new ShopError('تغییر وضعیت مجاز نیست.');if($status==='CANCELLED'&&$o['authority'])throw new ShopError('سفارش دارای authority باید ابتدا در درگاه بررسی شود.');query('UPDATE ns_orders SET status=? WHERE id=?',[$status,$id]);if($status==='CANCELLED')foreach(all('SELECT * FROM ns_order_items WHERE order_id=?',[$id])as$i)query('UPDATE ns_products SET stock=stock+?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?',[$i['quantity'],$i['product_id']]);});break;
 case 'order_update':
  $id=number_input('id',1,PHP_INT_MAX);$status=text_input('status');if(!in_array($status,['PENDING','PAID','SHIPPED','DELIVERED','EXPIRED','PAYMENT_REVIEW','CANCELLED'],true))throw new ShopError('وضعیت سفارش معتبر نیست.');
  query('UPDATE ns_orders SET name=?,phone=?,address=?,status=?,reference=? WHERE id=?',[text_input('name',3,120),phone_input(),text_input('address',10,2000),$status,text_input('reference',0,100),$id]);break;
 case 'delete_order':
  $id=number_input('id',1,PHP_INT_MAX);
  transaction(function()use($id){if(!one('SELECT id FROM ns_orders WHERE id=?',[$id]))throw new ShopError('سفارش پیدا نشد.',404);query('DELETE FROM ns_order_items WHERE order_id=?',[$id]);query('DELETE FROM ns_analytics_events WHERE order_id=?',[$id]);query('DELETE FROM ns_orders WHERE id=?',[$id]);});
  break;
 case 'verify_order':$id=number_input('id',1,PHP_INT_MAX);verify_order_payment($id);break;
 case 'review_moderate':$id=number_input('id',1,PHP_INT_MAX);$approved=number_input('approved',0,1);query('UPDATE ns_reviews SET approved=? WHERE id=?',[$approved,$id]);break;
 case 'review_delete':query('DELETE FROM ns_reviews WHERE id=?',[number_input('id',1,PHP_INT_MAX)]);break;
 case 'delete_support_thread':
  $id=number_input('id',1,PHP_INT_MAX);if(!one("SELECT id FROM ns_users WHERE id=? AND role='CUSTOMER'",[$id]))throw new ShopError('مشتری پیدا نشد.',404);
  query('DELETE FROM ns_support_messages WHERE user_id=?',[$id]);break;
 case 'clear_analytics':query('DELETE FROM ns_analytics_events');break;
 case 'delete_login_event':query('DELETE FROM ns_login_events WHERE id=?',[number_input('id',1,PHP_INT_MAX)]);break;
 case 'clear_login_history':query('DELETE FROM ns_login_events');break;
 case 'delete_audit_log':query('DELETE FROM ns_admin_audit WHERE id=?',[number_input('id',1,PHP_INT_MAX)]);break;
 case 'clear_audit_log':query('DELETE FROM ns_admin_audit');break;
 default:throw new ShopError('عملیات پیدا نشد.',404);
 }
 audit_admin(strtoupper($action),(string)($_POST['id']??''));$tab=['delete_product'=>'products','category'=>'categories','delete_category'=>'categories','customer'=>'customers','delete_customer'=>'customers','shipping'=>'shipping','company'=>'company','order'=>'orders','order_update'=>'orders','delete_order'=>'orders','verify_order'=>'orders','delete_article'=>'articles','review_moderate'=>'reviews','review_delete'=>'reviews','delete_support_thread'=>'support','clear_analytics'=>'analytics','delete_login_event'=>'history','clear_login_history'=>'history','delete_audit_log'=>'audit','clear_audit_log'=>'audit'][$action]??'dashboard';flash('تغییرات ذخیره شد.');redirect('/admin?tab='.$tab);
}
