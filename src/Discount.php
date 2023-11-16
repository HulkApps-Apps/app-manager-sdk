<?php

namespace HulkApps\AppManager;

use Cookie;

class Discount
{
    public function resolveFromCookies(): ?array
    {
        if (Cookie::has('ShopCircleDiscount') === true) {
            return [
                'codeType' => 'normal',
                'code' => Cookie::get('ShopCircleDiscount'),
            ];
        }

        return null;
    }

}
