<?php

return [
    /*
     | The guard used to log the user in after a successful SSO callback.
     */
    'guard' => env('THIJSSENSOFTWARE_ID_GUARD', 'web'),

    /*
     | The Eloquent user model to provision and authenticate.
     */
    'user_model' => env('THIJSSENSOFTWARE_ID_USER_MODEL', 'App\\Models\\User'),

    /*
     | Where to send the user after a successful sign-in (fallback for
     | redirect()->intended()).
     */
    'home' => env('THIJSSENSOFTWARE_ID_HOME', '/dashboard'),

    /*
     | Just-in-time provisioning, off here where the package defaults it on.
     |
     | The callback can only fill idp_id, name and email. A billr account also
     | needs a workspace, which RegisterFreelancer creates alongside the user;
     | provisioning from the callback alone yields a freelancer with a null
     | current_workspace_id, who passes the access-workspace gate (it only
     | checks isFreelancer) and then fails on every freelancer route.
     |
     | Accounts therefore have to exist before their first SSO sign-in. The
     | callback still links an existing account by email and signs it in.
     */
    'provision' => env('THIJSSENSOFTWARE_ID_PROVISION', false),
];
