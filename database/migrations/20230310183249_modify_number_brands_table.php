<?php
declare(strict_types = 1);

use Phinx\Migration\AbstractMigration;

final class ModifyNumberBrandsTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('number_brands');
        $table->addColumn('phone_num', 'string', ['null' => TRUE, 'comment' => '客服电话'])->save();
    }
}
