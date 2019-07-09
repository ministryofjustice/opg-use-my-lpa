<?php

declare(strict_types=1);

namespace App\DataAccess\Repository;

use App\Exception\NotFoundException;

interface ActorUsersInterface
{
    /**
     * Add an actor user
     *
     * @param string $email
     * @param string $password
     * @param string $activationToken
     * @param int $activationTtl
     * @return array
     */
    public function add(string $email, string $password, string $activationToken, int $activationTtl) : array;

    /**
     * Get an actor user from the database
     *
     * @param string $email
     * @return array
     * @throws NotFoundException
     */
    public function get(string $email) : array;

    /**
     * Get an actor user from the database using the token value
     *
     * @param string $activationToken
     * @return array
     */
    public function getByToken(string $activationToken) : array;

    /**
     * Check for the existence of an actor user
     *
     * @param string $email
     * @return bool
     */
    public function exists(string $email) : bool;
}
