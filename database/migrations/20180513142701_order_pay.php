<?php

use think\migration\Migrator;
use think\migration\db\Column;

class OrderPay extends Migrator
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
        if($this->hasTable('order_pay')){
            $table = $this->table('order_pay');
            $table->addColumn('pay_type', 'integer',array('limit' => 2,'default'=>0,'comment'=>'第三方第预支付方式 1 微信 2 支付宝 3 银行卡   只有第三方支付才使用此字段'))
                ->addColumn('pay_time', 'biginteger',array('limit' =>11,'default'=>0,'comment'=>'发起支付的时间戳'))
                ->update();
        }
    }
    /**
     *  提供回滚的方法
     */
    public function down(){
        $table = $this->table('order_pay');
        $table->removeColumn('pay_type')
            ->removeColumn('pay_time')
            ->update();
    }
}
