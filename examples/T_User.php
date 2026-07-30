<?php

class T_User
{


    /**
     * @transient
     */
    public string $username;
    public string $email = "default";
    public ?string $last_name;


    /**
     * @var T_Account
     */
    public $account2;

    public T_Account $account3;

    /**
     * @var T_Account[]
     */
    public array $accounts = [];

    /**
     * @var array<string, T_Account>
     */
    public array $accountMap = [];

}