<?php

use think\migration\Migrator;
use think\migration\db\Column;

class ThirdAuthToken extends Migrator
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
        if(!$this->hasTable('third_auth_token')){
            $table = $this->table('third_auth_token');
            $table->addColumn('access_token', 'string',array('limit' => 255,'default'=>'','comment'=>'接口访问token'))
                ->addColumn('token_expired', 'biginteger',array('limit' => 11,'default'=>null,'comment'=>'token失效时间'))
                ->addColumn('app_id', 'string',array('limit' => 32,'default'=>'','comment'=>'分配给第三方的id'))
                ->addColumn('app_secret', 'string',array('limit' => 32,'default'=>'','comment'=>'分配给第三方的密钥'))
                ->addColumn('third_name', 'string',array('limit' => 32,'default'=>'','comment'=>'平台名称'))
                ->addColumn('third_phone', 'biginteger',array('limit' => 11,'default'=>null,'comment'=>'平台联系电话'))
                ->addColumn('third_address', 'string',array('limit' => 200,'default'=>'','comment'=>'平台地址'))
                ->create();
        }
    }
    /**
     *  提供回滚的方法
     */
    public function down(){
        $table = $this->table('third_auth_token');
        $table->removeColumn('access_token')
            ->removeColumn('token_expired')
            ->removeColumn('app_id')
            ->removeColumn('app_secret')
            ->removeColumn('third_name')
            ->removeColumn('third_phone')
            ->removeColumn('third_address')
            ->update();
    }
}
