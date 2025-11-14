<?php
/**
 * LDAP / Active Directory configuration.
 *
 * Values can be overridden via environment variables:
 *  - ABBIS_LDAP_ENABLED (true/false)
 *  - ABBIS_LDAP_HOST (e.g. ldap://ad.example.com)
 *  - ABBIS_LDAP_PORT (e.g. 389 or 636)
 *  - ABBIS_LDAP_USE_TLS (true/false)
 *  - ABBIS_LDAP_BIND_DN (e.g. {username}@example.com or CN={username},OU=Users,DC=example,DC=com)
 *  - ABBIS_LDAP_SEARCH_BASE (e.g. OU=Users,DC=example,DC=com)
 *  - ABBIS_LDAP_SEARCH_FILTER (e.g. (sAMAccountName={username}))
 *  - ABBIS_LDAP_DEFAULT_ROLE (fallback ABBIS role when provisioning new LDAP users)
 *  - ABBIS_LDAP_TIMEOUT (connection timeout seconds)
 */

require_once __DIR__ . '/constants.php';

return [
    'enabled' => filter_var(getenv('ABBIS_LDAP_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'host' => getenv('ABBIS_LDAP_HOST') ?: 'ldap://localhost',
    'port' => intval(getenv('ABBIS_LDAP_PORT') ?: 389),
    'use_tls' => filter_var(getenv('ABBIS_LDAP_USE_TLS') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'bind_dn' => getenv('ABBIS_LDAP_BIND_DN') ?: '{username}@example.com',
    'search_base' => getenv('ABBIS_LDAP_SEARCH_BASE') ?: 'DC=example,DC=com',
    'search_filter' => getenv('ABBIS_LDAP_SEARCH_FILTER') ?: '(sAMAccountName={username})',
    'attributes' => [
        'email' => getenv('ABBIS_LDAP_ATTR_EMAIL') ?: 'mail',
        'full_name' => getenv('ABBIS_LDAP_ATTR_FULLNAME') ?: 'displayname',
        'first_name' => getenv('ABBIS_LDAP_ATTR_FIRSTNAME') ?: 'givenname',
        'last_name' => getenv('ABBIS_LDAP_ATTR_LASTNAME') ?: 'sn',
    ],
    'default_role' => getenv('ABBIS_LDAP_DEFAULT_ROLE') ?: ROLE_CLERK,
    'timeout' => intval(getenv('ABBIS_LDAP_TIMEOUT') ?: 5),
    'allow_local_fallback' => filter_var(getenv('ABBIS_LDAP_ALLOW_LOCAL_FALLBACK') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'auto_provision' => filter_var(getenv('ABBIS_LDAP_AUTO_PROVISION') ?: 'true', FILTER_VALIDATE_BOOLEAN),
];

