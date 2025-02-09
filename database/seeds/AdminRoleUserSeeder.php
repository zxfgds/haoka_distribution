<?php


use Phinx\Seed\AbstractSeed;

class AdminRoleUserSeeder extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run(): void
    {
        $data  = ['role_id' => 1, 'user_id' => 1];
        $table = $this->table('admin_role_user');
        $table->insert($data)->save();
    }
}
