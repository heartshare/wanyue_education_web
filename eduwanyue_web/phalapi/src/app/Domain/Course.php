<?php

// +----------------------------------------------------------------------
// | Created by Wanyue
// +----------------------------------------------------------------------
// | Copyright (c) 2017~2019 http://www.sdwanyue.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: https://gitee.com/WanYueKeJi
// +----------------------------------------------------------------------
// | Date: 2020/09/05 11:14
// +----------------------------------------------------------------------
namespace App\Domain;

use App\Domain\Teacher as Domain_Teacher;
use App\Model\Course as Model_Course;
use App\Model\User as Model_User;

class Course
{


    /* 课程科目分类 */
    public function getClass()
    {

        $key  = 'getcourseclass';
        $list = \App\getcaches($key);
        if (!$list) {
            $model = new Model_Course();
            $list  = $model->getClass();
            \App\setcaches($key, $list);
        }


        foreach ($list as $k => $v) {
            $v['thumb'] = \App\get_upload_path($v['thumb']);
            unset($v['list_order']);
            $list[$k] = $v;
        }

        return $list;
    }

    /* 某课程科目分类信息 */
    public function getClassInfo($id)
    {

        $info = [];
        $list = $this->getClass();

        foreach ($list as $k => $v) {
            if ($id == $v['id']) {
                $info = $v;
                break;
            }
        }

        return $info;
    }

    /* 内容形式 */
    protected function getTypes($k = '')
    {
        $type = [
            '1' => '图文',
            '2' => '视频',
            '3' => '音频',
        ];
        if ($k == '') {
            return $type;
        }
        return isset($type[$k]) ? $type[$k] : '';
    }

    /* 处理课程信息 */
    protected function handelInfo($v)
    {
        $v['thumb'] = \App\get_upload_path($v['thumb']);
        $payval     = '免费';
        $lesson     = '';
        $sort       = $v['sort'];
        /* 内容 */
        if ($sort == 0) {
            $lesson = $this->getTypes($v['type']);
        }

        /* 课程 */
        if ($sort == 1) {
            if ($v['lessons'] > 0) {
                $lesson = $v['lessons'] . '课时';
            }
        }

        /* 直播 */
        if ($sort >= 2) {
            if ($v['islive'] == 0) {
                $lesson = \App\handelsvctm($v['starttime']);
            }
            if ($v['islive'] == 1) {
                $lesson = '正在直播';
            }
            if ($v['islive'] == 2) {
                $lesson = '直播结束';
            }
        }

        $paytype = $v['paytype'];
        if ($paytype == 1) {
            $payval = $v['payval'];
        }

        if ($paytype == 2) {
            $payval = '密码';
        }

        $v['payval'] = $payval;
        $v['lesson'] = $lesson;
        unset($v['status']);
        unset($v['shelvestime']);
        unset($v['lessons']);
        unset($v['starttime']);

        if (isset($v['addtime'])) {
            $v['add_time'] = date('Y-m-d', $v['addtime']);
        }

        return $v;
    }

    /* 课程列表 */
    public function getList($p = 1, $where = '', $order = 'list_order asc,id desc', $nums = 0)
    {

        $model = new Model_Course();
        $list  = $model->getList($where, $order, $p, $nums);
        foreach ($list as $k => $v) {
            $v = $this->handelInfo($v);

            $userinfo           = \App\getUserInfo($v['uid']);
            $v['user_nickname'] = $userinfo['user_nickname'];
            $v['avatar']        = $userinfo['avatar'];
            $list[$k] = $v;
        }

        return $list;
    }

    /* 课程详情 */
    public function getDetail($uid, $where)
    {

        $rs = array('code' => 0, 'msg' => '', 'info' => array());

        $model = new Model_Course();
        $info  = $model->getDetail($where);

        if (!$info) {
            $rs['code'] = 1002;
            $rs['msg']  = \PhalApi\T('课程不存在');
            return $rs;
        }

        $info = $this->handelInfo($info);

        $Domain_Teacher   = new Domain_Teacher();
        $userinfo         = $Domain_Teacher->getInfo($uid, $info['uid']);
        $info['userinfo'] = \App\handleUser($userinfo);

        $ifbuy   = '0';
        $paytype = $info['paytype'];
        if ($paytype != 0) {
            $where2 = [
                'uid'      => $uid,
                'courseid' => $info['id'],
                'status'   => 1,
            ];
            $ispay  = $model->getBuy($where2);
            if ($ispay) {
                $ifbuy = '1';
            }
        }

        $info['ifbuy'] = $ifbuy;
        $info['url']   = \App\encryption(\App\get_upload_path($info['url']));

        unset($info['uptime']);

        /* 辅导老师 */
        $tutor = [];
        if ($info['tutoruid'] > 0) {
            $tutoruidinfo = $Domain_Teacher->getInfo($uid, $info['tutoruid']);
            $tutor[]      = \App\handleUser($tutoruidinfo);
        }
        unset($info['tutoruid']);

        $info['tutor'] = $tutor;

        /* 无套餐 */
        $info['ispack'] = '0';

        /*无购物车*/
        $info['iscart'] = '0';

        $rs['info'][0] = $info;

        return $rs;
    }

    /* 课程详情-未处理 */
    public function getDetaild($where, $field = '*')
    {

        $model = new Model_Course();
        $info  = $model->getDetail($where, $field);

        return $info;
    }

    /* 更新星级、评论数 */
    public function upStar($where, $star = 1, $comments = 1)
    {

        if (!$where) {
            return 0;
        }

        $model = new Model_Course();
        $rs    = $model->upStar($where, $star, $comments);


        return $rs;
    }

    /* 获取购买记录 */
    public function getBuy($where)
    {

        if (!$where) {
            return 0;
        }

        $model = new Model_Course();
        $info  = $model->getBuy($where);

        return $info;
    }

    /* 密码 */
    public function checkPass($uid, $pass, $courseid)
    {

        $rs = array('code' => 0, 'msg' => \PhalApi\T('操作成功'), 'info' => array());

        $where = [
            'id' => $courseid
        ];
        $model = new Model_Course();
        $info  = $model->getDetail($where);
        if (!$info) {
            $rs['code'] = 1002;
            $rs['msg']  = \PhalApi\T('课程不存在');
            return $rs;
        }

        if ($info['paytype'] == 0) {
            $rs['code'] = 1003;
            $rs['msg']  = \PhalApi\T('当前课程免费');
            return $rs;
        }

        if ($info['paytype'] == 1) {
            $rs['code'] = 1003;
            $rs['msg']  = \PhalApi\T('当前课程为付费内容');
            return $rs;
        }

        if ($pass != $info['payval']) {
            $rs['code'] = 1003;
            $rs['msg']  = \PhalApi\T('密码错误');
            return $rs;
        }

        $where2  = [
            'uid'      => $uid,
            'courseid' => $courseid,
        ];
        $payinfo = $model->getBuy($where2);
        if (!$payinfo) {
            $data = [
                'uid'      => $uid,
                'sort'     => $info['sort'],
                'paytype'  => $info['paytype'],
                'courseid' => $courseid,
                'liveuid'  => $info['uid'],
                'status'   => 1,
                'addtime'  => time(),
                'paytime'  => time(),
            ];
            $model->addBuy($data);
        } else {
            $data = [
                'sort'    => $info['sort'],
                'paytype' => $info['paytype'],
                'status'  => 1,
                'paytime' => time(),
            ];
            $model->upBuy($where2, $data);
        }

        return $rs;
    }

    /* 课时列表 */
    public function getLessonList($uid, $courseid)
    {

        $nowtime = time();
        $model   = new Model_Course();

        $where2 = ['id' => $courseid];
        $info   = $model->getDetail($where2);
        if (!$info) {
            return [];
        }

        $mode = $info['mode'];

        $where    = ['uid' => $uid, 'courseid' => $courseid];
        $viewlist = $model->getView($where);

        $nums   = count($viewlist);
        $lastid = 0;
        if ($viewlist) {
            $lastid = $viewlist[0]['lessonid'];
        }

        $ispay = 1;

        if ($info['paytype'] != 0) {
            $where2 = ['uid' => $uid, 'courseid' => $courseid, 'status' => 1];
            $isbuy  = $model->getBuy($where2);
            if (!$isbuy) {
                $ispay = 0;
            }
        }

        $where = ['courseid' => $courseid];
        $list  = $model->getLessonList($where);

        $list = $this->handelLesson($list, 0);
        $num  = 0;
        foreach ($list as $k => $v) {
            foreach ($v['list'] as $k1 => $v1) {

                $isenter = '1';
                if ($ispay != 1) {
                    /* 未购买不能进 */
                    $isenter = '0';
                }
                if ($mode == 1 && $num > $nums) {
                    /* 待解锁 不能进 */
                    $isenter = '0';
                }
                if ($v1['istrial'] == 1) {
                    /* 试学 可进 */
                    $isenter = '1';
                }

                if ($v1['type'] >= 4 && $v1['islive'] == 0) {
                    /* 未直播  不能进 */
                    $isenter = '0';
                }
                $v1['isenter'] = $isenter;


                $status = '0';
                if ($v1['istrial'] == 1) {
                    /* 试学 */
                    $status = '1';
                }

                foreach ($viewlist as $k2 => $v2) {
                    if ($v2['lessonid'] == $v1['id']) {
                        /* 已学完 */
                        $status = '2';
                    }
                }
                if ($v1['islive'] == 1) {
                    /* 在直播 */
                    $status = '3';
                }

                if ($mode == 1 && $num > $nums) {
                    /* 待解锁 */
                    $status = '4';
                }
                $v1['status'] = $status;


                $islast = '0';
                if ($lastid == $v1['id']) {
                    $islast = '1';
                }

                $v1['islast'] = $islast;


                $time = '';
                if ($v1['type'] >= 4) {
                    //if($v1['islive']==0){
                    $time = date('m月d日 H:i', $v1['starttime']);
                    //}
                }
                $v1['time_date'] = $time;
                $v1['url']       = \App\encryption(\App\get_upload_path($v1['url']));

                $v['list'][$k1] = $v1;
                $num++;
            }
            $list[$k] = $v;
        }


        return $list;
    }

    /* 处理课时数组 */
    protected function handelLesson($list = [], $pid = 0)
    {
        $rs = [];
        foreach ($list as $k => $v) {
            if ($v['pid'] == $pid) {
                unset($list[$k]);
                $v['list'] = $this->handelLesson($list, $v['id']);
                $rs[]      = $v;
            }
        }

        return $rs;
    }


    /* 已购买课程 */
    public function getMyBuy($uid, $sort, $p = 1)
    {

        $nowtime = time();
        if (!empty($sort)) {
            if ($sort == -1) {
                $where = [
                    'uid'    => $uid,
                    'sort'   => 0,
                    'status' => 1,
                ];
            } else if ($sort == 2) {
                $where = [
                    'uid'     => $uid,
                    'sort>=?' => $sort,
                    'status'  => 1,
                ];
            }
        } else {
            $where = [
                'uid'      => $uid,
                'sort !=?' => 1,
                'status'   => 1,
            ];
        }
        $list2 = [];
        $model = new Model_Course();
        $list  = $model->getBuyList($p, $where);

        foreach ($list as $k => $v) {

            $where2 = ['id' => $v['courseid']];
            $info   = $model->getDetail($where2);
            if (!$info) {
                continue;
            }
            $info2 = [
                'id'        => $info['id'],
                'uid'       => $info['uid'],
                'sort'      => $info['sort'],
                'type'      => $info['type'],
                'name'      => $info['name'],
                'thumb'     => $info['thumb'],
                'paytype'   => $info['paytype'],
                'payval'    => $info['payval'],
                'status'    => $info['status'],
                'starttime' => $info['starttime'],
                'lessons'   => $info['lessons'],
                'islive'    => $info['islive'],
            ];
            $info2 = $this->handelInfo($info2);

            $userinfo               = \App\getUserInfo($info2['uid']);
            $info2['user_nickname'] = $userinfo['user_nickname'];
            $info2['avatar']        = $userinfo['avatar'];

            $tips    = '免费';
            $paytype = $v['paytype'];
            if ($paytype == 1) {
                $tips = '已付费';
            }
            if ($paytype == 2) {
                $tips = '密码';
            }

            $info2['payval'] = $tips;

            $list2[] = $info2;

        }

        return $list2;
    }

    /* 已购买课程ID */
    public function getMyBuyIds($uid)
    {

        $where = [
            'uid'    => $uid,
            'status' => 1,
        ];
        $model = new Model_Course();
        $list  = $model->getMyBuyIds($where);

        $list2 = [];
        if ($list) {
            $list2 = array_column($list, 'courseid');
        }

        return $list2;
    }

    /* 课程学级分类 */
    public function getGradeList()
    {
        $key  = 'getcoursegrade';
        $list = \App\getcaches($key);
        if (!$list) {
            $model = new Model_Course();
            $list  = $model->getGrade();
            \App\setcaches($key, $list);
        }

        foreach ($list as $k => $v) {
            unset($v['list_order']);
            $list[$k] = $v;
        }

        return $list;
    }

    /* 默认学级分类 */
    public function getGradeDef()
    {

        $rs = array('code' => 0, 'msg' => '', 'info' => array());

        $info = [
            'id'   => '0',
            'name' => '',
        ];

        $list = $this->getGradeList();
        $list = $this->handelGrade($list);

        $isset = 0;
        foreach ($list as $k => $v) {
            if ($isset) {
                break;
            }
            foreach ($v['list'] as $k1 => $v1) {
                $info['id']   = (string)$v1['id'];
                $info['name'] = $v1['name'];
                $isset        = 1;
                break;
            }
        }

        $rs['info'][0] = $info;

        return $rs;
    }

    /* 层级格式化学级分类 */
    public function getGrade()
    {

        $list = $this->getGradeList();
        $list = $this->handelGrade($list);

        return $list;
    }

    /* 处理课时学级层级 */
    protected function handelGrade($list = [], $pid = 0)
    {
        $rs = [];
        foreach ($list as $k => $v) {
            if ($v['pid'] == $pid) {
                unset($list[$k]);
                $v['list'] = $this->handelGrade($list, $v['id']);
                $rs[]      = $v;
            }
        }

        return $rs;
    }

    /* 某学级分类信息 */
    public function getGradeInfo($id)
    {

        $info = [];
        $list = $this->getGradeList();
        foreach ($list as $k => $v) {
            if ($v['id'] == $id) {
                $info = $v;
                break;
            }
        }
        return $info;
    }

    /* 课程学级分类 */
    public function setGrade($uid, $gradeid)
    {

        $rs = array('code' => 0, 'msg' => '', 'info' => array());

        $model = new Model_Course();

        $where = ['id' => $gradeid];
        $info  = $model->getGradeInfo($where);
        if (!$info) {
            $rs['code'] = 1002;
            $rs['msg']  = \PhalApi\T('信息错误');
            return $rs;
        }

        if ($info['pid'] == 0) {
            $rs['code'] = 1003;
            $rs['msg']  = \PhalApi\T('信息错误');
            return $rs;
        }

        $data       = ['gradeid' => $gradeid];
        $Model_User = new Model_User();
        $result     = $Model_User->upUserInfo($uid, $data);

        if ($result === false) {
            $rs['code'] = 1004;
            $rs['msg']  = \PhalApi\T('设置失败，请重试');
            return $rs;
        }

        return $rs;
    }
}
