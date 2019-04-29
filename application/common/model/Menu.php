<?php
namespace app\common\model;
use think\Model;
class Menu extends Model
{
    protected static function init(){
        self::beforeWrite(function ($user) {
            cache('menu', NULL);
        });
        self::afterDelete(function ($user) {
            cache('menu', NULL);
        });

        //保持缓存
        $menu = cache("menu");
        if (empty($result)) {
            $menu = model('menu')->order(array("list_order" => "ASC"))->select()->toArray();
            cache('menu',$menu);
        }
    }

    //验证action是否重复添加
    public function checkAction($data) {
        //检查是否重复添加
        $find = $this->where($data)->find();
        if ($find) {
            return false;
        }
        return true;
    }

    //验证菜单是否超出三级
    public function checkParentid($parentid) {
        $find = $this->where(array("id" => $parentid))->value("parent_id");
        if ($find) {
            $find2 = $this->where(array("id" => $find))->value("parent_id");
            if ($find2) {
                $find3 = $this->where(array("id" => $find2))->value("parent_id");
                if ($find3) {
                    return false;
                }
            }
        }
        return true;
    }

    //验证action是否重复添加
    public function checkActionUpdate($data) {
        //检查是否重复添加
        $id=$data['id'];
        unset($data['id']);
        $find = $this->field('id')->where($data)->find();
        if (isset($find['id']) && $find['id']!=$id) {
            return false;
        }
        return true;
    }

	/**
     * 菜单树状结构集合
     */
    public function menu_json() {
        $data = $this->get_tree(0);
        return $data;
    }

    //取得树形结构的菜单
    public function get_tree($myid, $parent = "", $Level = 1) {
        $data = $this->admin_menu($myid);
        $Level++;
        if (is_array($data)) {
            $ret = NULL;
            foreach ($data as $a) {
                $id = $a['id'];
                $name = $a['app'];
                $model = $a['model'];
                $action = $a['action'];
                //附带参数
              	$params = "";
                if ($a['url_param']) {
                    $params = "?" . htmlspecialchars_decode($a['data']);
                }
                $array = array(
                    "icon" => $a['icon'],
                    "id" => $name,
                    "name" => $a['name'],
                    "parent" => $parent,
                    "url" => url("{$name}/{$model}/{$action}{$params}")
                ); 
                
                
                
                $ret[$id . $name] = $array;
                $child = $this->get_tree($a['id'], $id, $Level);
                //由于后台管理界面只支持三层，超出的不层级的不显示
                if ($child && $Level <= 3) {
                    $ret[$id . $name]['items'] = $child;
                }
               
            }
            return $ret;
        }
       
        return false;
    }

    /**
     * 按父ID查找菜单子项
     * @param integer $parentid   父菜单ID  
     * @param integer $with_self  是否包括他自己
     */
    public function admin_menu($parentid, $with_self = false) {
        //父节点ID
        $parentid = (int) $parentid;
        $menu = cache('menu');
        $result = array();
        foreach ($menu as $key => $value) {
            if ($value['parent_id'] == $parentid && $value['status'] == 1) {
                $result[] = $value;
            }
        }

        if ($with_self) {
            $result2 = array();
            foreach ($menu as $key => $value) {
                if ($value['id'] == $parentid) {
                    $result2[] = $value;
                }
            }
            $result = array_merge($result2, $result);
        }
        //权限检查
        if (sp_get_current_admin_id() == 1) {
            //如果是超级管理员 直接通过
            return $result;
        } 
        $Auth = new \Rbac\Auth;
        $array = array();
        foreach ($result as $v) {
        	
            //方法
            $action = $v['action'];
            
            $rule_name=strtolower($v['app']."/".$v['model']."/".$action);
            if ($Auth->check(sp_get_current_admin_id(),$rule_name)){
            	$array[] = $v;
            }
        } 
        
        return $array;
    }
}