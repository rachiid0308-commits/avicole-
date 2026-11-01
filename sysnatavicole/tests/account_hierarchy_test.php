<?php
declare(strict_types=1);
require_once __DIR__ . '/../htdocs/account_manager.php';
require_once __DIR__ . '/../htdocs/controls_domain.php';
function ah_check(bool $condition,string $message):void { if (!$condition) { fwrite(STDERR,"FAIL: {$message}\n"); exit(1); } }
$central=['id'=>1,'role'=>ROLE_CENTRAL];
$admin=['id'=>2,'role'=>ROLE_ADMINISTRATOR];
$viewer=['id'=>3,'role'=>ROLE_CENTRAL_VIEWER];
$regional=['id'=>4,'role'=>ROLE_REGIONAL,'region_id'=>8];
$division=['id'=>5,'role'=>ROLE_DIVISION_HEAD,'region_id'=>8];
$service=['id'=>6,'role'=>ROLE_PROVINCIAL,'region_id'=>8,'province_id'=>31];
$vet=['id'=>7,'role'=>ROLE_VETERINARY_RESPONSIBLE,'region_id'=>8,'province_id'=>31];
$otherVet=['id'=>8,'role'=>ROLE_VETERINARY_RESPONSIBLE,'region_id'=>8,'province_id'=>32];
$otherDivision=['id'=>9,'role'=>ROLE_DIVISION_HEAD,'region_id'=>9];
ah_check(!managed_account_roles($central)&&!managed_account_roles($viewer)&&!managed_account_roles($regional)&&!managed_account_roles($division)&&!managed_account_roles($service)&&!managed_account_roles($vet),'only the administrator can receive account-management roles');
ah_check(count(managed_account_roles($admin))===7,'administrator can assign every supported profile');
ah_check(can_manage_account($admin,$central)&&can_manage_account($admin,$viewer)&&can_manage_account($admin,$otherDivision)&&can_manage_account($admin,$otherVet),'administrator manages accounts in every scope');
foreach ([$central,$viewer,$regional,$division,$service,$vet] as $readOnlyProfile) ah_check(!can_manage_account($readOnlyProfile,$admin),'non-administrator profiles cannot manage accounts');
ah_check(has_global_scope($central)&&has_global_scope($admin)&&has_global_scope($viewer)&&!has_global_scope($regional)&&!has_global_scope($division),'only central, administrator and central-viewer profiles have national read scope');
ah_check(!can_manage_farm($central)&&!can_manage_farm($regional)&&!can_manage_farm($division)&&!can_manage_farm($vet)&&can_manage_farm($admin)&&can_manage_farm($service),'only administrator and Chef de service manage farm records');
ah_check(can_access_farm($regional,['region_id'=>8,'province_id'=>999])&&can_access_farm($division,['region_id'=>8,'province_id'=>999])&&!can_access_farm($regional,['region_id'=>99,'province_id'=>999])&&!can_access_farm($division,['region_id'=>99,'province_id'=>999]),'regional and division profiles can consult farms only in their assigned region');
[$scope,$params]=account_list_scope($central); ah_check($scope==='1=1'&&$params===[],'central profile reads national account list');
[$scope,$params]=account_list_scope($viewer); ah_check($scope==='1=1'&&$params===[],'central-viewer profile reads national account list');
[$scope,$params]=account_list_scope($regional); ah_check($scope==='u.region_id=:account_region'&&$params[':account_region']===8,'regional profile sees accounts only in its region');
[$scope,$params]=account_list_scope($division); ah_check($scope==='u.region_id=:account_region'&&$params[':account_region']===8,'division-head profile sees accounts only in its region');
[$scope,$params]=account_list_scope($service); ah_check($scope==='u.province_id=:account_province'&&$params[':account_province']===31,'provincial profile sees accounts in its own province');
ah_check(can_manage_controls($admin)&&can_manage_controls($service)&&can_manage_controls($vet)&&!can_manage_controls($viewer),'only administrator, service and veterinarian write controls');
ah_check(can_print_reports($admin)&&can_print_reports($service)&&can_print_reports($vet)&&!can_print_reports($central)&&!can_print_reports($viewer)&&!can_print_reports($regional)&&!can_print_reports($division),'administrator, Chef de service and veterinarian can print scoped reports');
ah_check(can_download_word_reports($admin)&&!can_download_word_reports($service)&&!can_download_word_reports($vet)&&!can_download_word_reports($central),'Word export remains administrator-only');
ah_check(!can_import_farm($central)&&!can_import_farm($viewer)&&!can_import_farm($regional)&&!can_import_farm($service)&&can_import_farm($admin),'only administrator can import data');
$installer=file_get_contents(__DIR__.'/../htdocs/install.php')?:'';$loginSource=file_get_contents(__DIR__.'/../htdocs/login.php')?:'';
ah_check(str_contains($installer,"'email' => 'basta@exemple.com'")&&str_contains($installer,"':role' => ROLE_ADMINISTRATOR")&&str_contains($installer,'password_hash($password, PASSWORD_DEFAULT)')&&str_contains($installer,'hash_equals($password, $confirmation)')&&str_contains($installer,'if ((int)$pdo->query(\'SELECT COUNT(*) FROM users\')->fetchColumn() > 0) $setupLocked = true;')&&str_contains($installer,'Premier administrateur')&&str_contains($loginSource,'compte administrateur initial'),'first-run setup securely creates the requested admin email with a user-chosen confirmed password, hashed and one-time locked');
$contact=validate_account_contact_fields('Agent.Exemple@ONSSA.GOV.MA','0612345678',' Vétérinaire ');
ah_check($contact===['agent.exemple@onssa.gov.ma','0612345678','Vétérinaire'],'account contact values are normalized and accepted for the ONSSA domain');
foreach ([['agent@example.com','0612345678'],['agent@onssa.gov.ma','061234567'],['agent@onssa.gov.ma','0612345678x']] as [$email,$phone]) {
    try { validate_account_contact_fields($email,$phone,null); ah_check(false,'invalid account email/mobile is rejected'); }
    catch (InvalidArgumentException) { }
}
$usersPage=file_get_contents(__DIR__.'/../htdocs/users.php')?:'';$editPage=file_get_contents(__DIR__.'/../htdocs/user_edit.php')?:'';$schema=file_get_contents(__DIR__.'/../htdocs/database/schema.sql')?:'';$migration=file_get_contents(__DIR__.'/../htdocs/database/account_contact_fields.sql')?:'';
ah_check(str_contains($usersPage,'professional_title')&&str_contains($usersPage,'mobile_phone')&&str_contains($usersPage,'@onssa\\.gov\\.ma')&&str_contains($usersPage,'LIMIT :limit OFFSET :offset'),'account creation and listing include title, ONSSA email, mobile, and 20-row pagination');
ah_check(str_contains($editPage,'mobile_phone')&&str_contains($editPage,'professional_title')&&str_contains($schema,'mobile_phone VARCHAR(10)')&&str_contains($schema,'professional_title VARCHAR(120)')&&str_contains($migration,'INFORMATION_SCHEMA.COLUMNS'),'account edits and both fresh/existing database paths include contact fields');
echo "OK: administrator-only account management, scoped account visibility, role permissions, secure setup, ONSSA email and mobile validation\n";
