<?php
echo "user_ini.filename=", get_cfg_var('user_ini.filename'), "\n";
echo "user_ini.cache_ttl=", get_cfg_var('user_ini.cache_ttl'), "\n";
phpinfo(INFO_GENERAL);
