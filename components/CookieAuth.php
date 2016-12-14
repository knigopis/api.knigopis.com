<?php

namespace app\components;

use yii\filters\auth\AuthMethod;

/**
 * CookieAuth is authenticator's method to login user by session or cookie
 */
class CookieAuth extends AuthMethod
{

    /**
     * @inheritdoc
     */
    public function authenticate($user, $request, $response)
    {
        $identity = $user->getIdentity();
        if ($identity === null) {
            $this->handleFailure($response);
        } else {
            return $identity;
        }

        return null;
    }
}
