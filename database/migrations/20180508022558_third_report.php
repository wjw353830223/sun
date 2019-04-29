<?php

use think\migration\Migrator;
use think\migration\db\Column;

class ThirdReport extends Migrator
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
        if(!$this->hasTable('third_report')){
            $table = $this->table('third_report');
            $table->addColumn('auth_token_id', 'integer',array('limit' => 11,'default'=>null,'comment'=>'third_auth表id'))
                ->addColumn('file_path', 'string',array('limit' => 200,'default'=>'','comment'=>'检测报告路径'))
                ->addColumn('upload_ip', 'string',array('limit' => 16,'default'=>'','comment'=>'上传ip'))
                ->addColumn('create_time', 'biginteger',array('limit' => 11,'default'=>null,'comment'=>'上传时间'))
                ->addColumn('member_health_id', 'biginteger',array('limit' => 11,'default'=>0,'comment'=>'第三方用户id'))
                ->create();
        }
    }
    /**
     *  提供回滚的方法
     */
    public function down(){
        $table = $this->table('third_report');
        $table->removeColumn('auth_token_id')
            ->removeColumn('file_path')
            ->removeColumn('upload_ip')
            ->removeColumn('create_time')
            ->removeColumn('member_health_id')
            ->update();
    }
}
