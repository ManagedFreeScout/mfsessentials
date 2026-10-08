<?php
// Card #194 (1.3.0): licence checks go through the Managed FreeScout hub, never straight to
// invAIse: a self-hosted FreeScout must not hold StackPros' invAIse credentials (msteamsfs
// card #285; same reasoning as MSTeamsFS's hub proxy, card #268). The hub calls invAIse with
// its own credentials, limited to the MFSEssentials product.
return [
    'hub_url'   => env('MFSESSENTIALS_HUB_URL', 'https://app.managedfreescout.com'),
    // Where admins can buy or renew a licence (shown on the settings page).
    'buy_url'   => env('MFSESSENTIALS_BUY_URL', 'https://managedfreescout.com/mfsessentials/'),
    'terms_url' => 'https://managedfreescout.com/terms-and-conditions/',
];
