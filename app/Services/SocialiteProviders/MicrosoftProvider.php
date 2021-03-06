<?php

namespace App\Services\SocialiteProviders;


use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class MicrosoftProvider extends AbstractProvider implements ProviderInterface{

    /**
     * Unique Provider Identifier.
     */
    const IDENTIFIER = 'LIVE';
    /**
     * {@inheritdoc}
     */
    protected $scopes = ['User.Read User.ReadBasic.All'];
    /**
     * {@inheritdoc}
     */
    protected $scopeSeparator = ' ';
    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(
            'https://login.microsoftonline.com/common/oauth2/v2.0/authorize', $state
        );
    }
    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    }
    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get(
            'https://graph.microsoft.com/v1.0/me',
            ['headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);
        return json_decode($response->getBody()->getContents(), true);
    }
    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id'       => $user['id'],
            'nickname' => null,
            'name'     => $user['displayName'],
            'email'    => $user['userPrincipalName'],
            'avatar'   => null,
            'user' => [
                'id'       => $user['id'],
                'nickname' => null,
                'given_name' => $user['givenName'],
                'family_name' => $user['surname'],
                'name'     => $user['displayName'],
                'email'    => $user['userPrincipalName'],
                'avatar'   => null,
            ]
        ]);
    }
    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }



}

