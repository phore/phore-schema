<?php

class T_User2
{

    public function __construct(
        /**
         * @transient
         */
        public string $username,
        public string $email = "default",
        public ?string $last_name = null,

        /**
         * @var T_Account[]
         */
        public array $accounts = [],

        /**
         * @var array<string, T_Account>
         */
        public array $accountMap = []
    ) {
    }

}