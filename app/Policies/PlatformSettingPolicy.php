<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlatformAdmin;
use App\Models\PlatformSetting;

class PlatformSettingPolicy
{
    public function view(PlatformAdmin $admin): bool
    {
        return true;
    }

    public function update(PlatformAdmin $admin, PlatformSetting $setting): bool
    {
        return true;
    }
}
