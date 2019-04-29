<?php

use think\migration\Migrator;
use think\migration\db\Column;

class ThirdMemberhealth extends Migrator
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function up(){
        if(!$this->hasTable('third_member_health')){
            $table = $this->table('third_member_health');
            $table->addColumn('member_name', 'string',array('limit' => 11,'default'=>'','comment'=>'用户姓名'))
                ->addColumn('member_height', 'integer',array('limit' => 7,'default'=>0,'comment'=>'用户身高'))
                ->addColumn('member_weight', 'integer',array('limit' => 7,'default'=>0,'comment'=>'会员体重(kg)'))
                ->addColumn('member_age', 'integer',array('limit' => 5,'default'=>0,'comment'=>'会员年龄'))
                ->addColumn('member_sex', 'integer',array('limit' => 1,'default'=>1,'comment'=>'会员性别 1男 2女'))
                ->addColumn('auth_token_id', 'biginteger',array('limit' => 11,'default'=>0,'comment'=>'第三方认证机构id'))
                ->create();
        }
    }
    /**
     *  提供回滚的方法
     */
    public function down(){
        $table = $this->table('third_member_health');
        $table->removeColumn('member_name')
            ->removeColumn('member_height')
            ->removeColumn('member_weight')
            ->removeColumn('member_age')
            ->removeColumn('member_sex')
            ->removeColumn('auth_token_id')
            ->update();
    }
}
