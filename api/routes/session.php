<?php
declare(strict_types=1);
/** Who is logged in + the CSRF token + the sidebar entity groups. Safe to call when logged out. */

$config = [
    'allowedEmailDomain' => ALLOWED_EMAIL_DOMAIN,
    'currency'           => CURRENCY,
    'codeTtl'            => CODE_TTL_MINUTES,
];

if ($DB_ERROR !== null) {
    out(['csrf' => csrf(), 'user' => null, 'config' => $config, 'dbError' => $DB_ERROR]);
}

$u = current_user();
$nav = [];
$pending = 0;
if ($u) {
    $groups = [
        'people' => ['People', 'users'],
        'in'     => ['Money in', 'coins'],
        'plan'   => ['Planning and spending', 'folder'],
    ];
    $icons = [
        'student' => 'student', 'officer' => 'badge', 'fund_source' => 'wallet', 'collection' => 'receipt',
        'budget' => 'chart', 'project' => 'folder', 'expense_category' => 'tag', 'expense' => 'receipt',
    ];
    if (in_array($u['level'], ['admin', 'mayor'], true)) {
        foreach ($groups as $g => [$gname, $gicon]) {
            $items = [];
            foreach (entities() as $key => $en) {
                if ($en['group'] !== $g || $key === 'officer' || ($u['level'] === 'mayor' && $key === 'student')) continue;
                $items[] = ['key' => $key, 'title' => $en['title'], 'icon' => $icons[$key] ?? $gicon];
            }
            $nav[] = ['title' => $gname, 'items' => $items];
        }
    }
    $pending = $u['level'] === 'admin' ? pending_requests() : 0;
}

out([
    'csrf'    => csrf(),
    'user'    => $u ? user_payload($u) : null,
    'nav'     => $nav,
    'pending' => $pending,
    'config'  => $config,
]);
