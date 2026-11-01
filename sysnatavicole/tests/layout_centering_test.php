<?php
declare(strict_types=1);
$css=file_get_contents(__DIR__.'/../htdocs/assets/reference-theme.css')?:'';
function layout_check(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}}
layout_check(str_contains($css,'html body>main{width:100%;max-width:1600px;margin:0 auto;'),'desktop page content is centered without reserving hidden-sidebar space');
layout_check(str_contains($css,'html body>.copyright-footer{width:100%;max-width:1600px;margin:0 auto;'),'footer aligns with the centered page content');
layout_check(!str_contains($css,'margin:0 0 0 252px')&&!str_contains($css,'width:calc(100% - 252px)'),'hidden sidebar no longer leaves a 252px offset');
layout_check(str_contains($css,'@media(max-width:850px)')&&str_contains($css,'html body>main{margin:0;'),'mobile layout keeps its responsive main positioning');
layout_check(str_contains($css,'html body>header .header-logout{position:fixed;z-index:1001;top:calc(env(safe-area-inset-top) + 10px);right:calc(env(safe-area-inset-right) + 12px);'),'mobile logout control stays fixed at the upper right');
layout_check(str_contains($css,'html body>header .brand{flex:1 1 calc(100% - 108px);max-width:calc(100% - 108px)}'),'mobile brand leaves space for the logout control');
echo "OK: selected-tab pages are centered after the sidebar collapses\n";
