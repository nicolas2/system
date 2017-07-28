<?php

namespace Nova\Auth;

use Nova\Auth\Contracts\UserInterface;
use Nova\Auth\Contracts\UserProviderInterface;
use Nova\Database\Connection;
use Nova\Hashing\HasherInterface;


class DatabaseUserProvider implements UserProviderInterface
{
    /**
     * The active database connection.
     *
     * @var \Nova\Database\Connection
     */
    protected $conn;

    /**
     * The hasher implementation.
     *
     * @var \Nova\Hashing\HasherInterface
     */
    protected $hasher;

    /**
     * The table containing the users.
     *
     * @var string
     */
    protected $table;

    /**
     * Create a new database user provider.
     *
     * @param  \Nova\Database\Connection  $conn
     * @param  \Nova\Hashing\HasherInterface  $hasher
     * @param  string  $table
     * @return void
     */
    public function __construct(Connection $conn, HasherInterface $hasher, $table)
    {
        $this->conn = $conn;
        $this->table = $table;
        $this->hasher = $hasher;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Nova\Auth\Contracts\UserInterface|null
     */
    public function retrieveById($identifier)
    {
        $user = $this->conn->table($this->table)->find($identifier);

        if (! is_null($user))
        {
            return new GenericUser((array) $user);
        }
    }

    /**
     * Retrieve a user by by their unique identifier and "remember me" token.
     *
     * @param  mixed   $identifier
     * @param  string  $token
     * @return \Nova\Auth\Contracts\UserInterface|null
     */
    public function retrieveByToken($identifier, $token)
    {
        $user = $this->conn->table($this->table)
                                ->where('id', $identifier)
                                ->where('remember_token', $token)
                                ->first();

        if (! is_null($user))
        {
            return new GenericUser((array) $user);
        }
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Nova\Auth\Contracts\UserInterface  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(UserInterface $user, $token)
    {
        $this->conn->table($this->table)
                            ->where('id', $user->getAuthIdentifier())
                            ->update(array('remember_token' => $token));
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \Nova\Auth\Contracts\UserInterface|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        // First we will add each credential element to the query as a where clause.
        // Then we can execute the query and, if we found a user, return it in a
        // generic "user" object that will be utilized by the Guard instances.
        $query = $this->conn->table($this->table);

        foreach ($credentials as $key => $value)
        {
            if (! str_contains($key, 'password'))
            {
                $query->where($key, $value);
            }
        }

        // Now we are ready to execute the query to see if we have an user matching
        // the given credentials. If not, we will just return nulls and indicate
        // that there are no matching users for these given credential arrays.
        $user = $query->first();

        if (! is_null($user))
        {
            return new GenericUser((array) $user);
        }
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Nova\Auth\Contracts\UserInterface  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(UserInterface $user, array $credentials)
    {
        $plain = $credentials['password'];

        return $this->hasher->check($plain, $user->getAuthPassword());
    }

}
