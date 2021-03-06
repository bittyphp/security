<?php

namespace Bitty\Security\Shield;

use Bitty\Http\RedirectResponse;
use Bitty\Security\Shield\AbstractShield;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class FormShield extends AbstractShield
{
    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request): ?ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if ($path === $this->config['login.path_post']) {
            return $this->handleFormLogin($request);
        }

        if ($path === $this->config['logout.path']) {
            $user = $this->context->get('user');

            $this->context->clear();
            $this->triggerEvent('security.logout', $user);

            return new RedirectResponse($this->config['logout.target']);
        }

        $roles = $this->context->getRoles($request);
        if (empty($roles)) {
            return null;
        }

        $user = $this->context->get('user');
        if ($user) {
            $user = $this->authenticator->reloadUser($user);
        }

        if (!$user) {
            if ($this->config['login.use_referrer']) {
                $this->context->set('login.target', $path);
            }

            return new RedirectResponse($this->config['login.path']);
        }

        $this->authorize($user, $roles);

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultConfig(): array
    {
        return [
            // Path to the login page.
            'login.path' => '/login',

            // Path to the login POST.
            'login.path_post' => '/login',

            // Path to go to after logging in, unless use_referrer is enabled.
            'login.target' => '/',

            // Name of element to get username from.
            'login.username' => 'username',

            // Name of element to get password from.
            'login.password' => 'password',

            // Whether or not to redirect to the referring page after login.
            'login.use_referrer' => true,

            // Path to the logout page.
            'logout.path' => '/logout',

            // Path to go to after logging out.
            'logout.target' => '/',
        ];
    }

    /**
     * Handles form logins.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface|null
     */
    private function handleFormLogin(ServerRequestInterface $request): ?ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
            return null;
        }

        $params = $request->getParsedBody();
        if (!is_array($params)) {
            return null;
        }

        $usernameField = $this->config['login.username'];
        $passwordField = $this->config['login.password'];

        $username = empty($params[$usernameField]) ? '' : $params[$usernameField];
        $password = empty($params[$passwordField]) ? '' : $params[$passwordField];

        if (empty($username) || empty($password)) {
            return null;
        }

        $this->authenticate($username, $password);

        $target = $this->config['login.target'];
        if ($this->config['login.use_referrer']) {
            $target = $this->context->get('login.target', $target);
            $this->context->remove('login.target');
        }

        return new RedirectResponse($target);
    }
}
