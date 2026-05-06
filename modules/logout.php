<?php

declare(strict_types=1);

auth_logout();
header('Location: ?module=login' . i18n_lang_query());
exit;
