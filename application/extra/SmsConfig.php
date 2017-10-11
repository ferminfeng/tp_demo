
<?php
use think\Env;
return [
    //漫道账号、密码
    'md_username' => Env::get('SmsMd.username'),
    'md_password' => Env::get('SmsMd.password'),

    //畅卓账号、密码
    'cz_username' => Env::get('SmsCz.username'),
    'cz_password' => Env::get('SmsCz.password'),
    'cz_username_tz' => Env::get('SmsCz.username_tz'),
    'cz_password_tz' => Env::get('SmsCz.password_tz'),
];
