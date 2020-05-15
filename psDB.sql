-- phpMyAdmin SQL Dump
-- version 4.9.2
-- https://www.phpmyadmin.net/
--
-- 主机： 127.0.0.1
-- 生成日期： 2020-05-09 03:23:29
-- 服务器版本： 5.7.28
-- PHP 版本： 7.3.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `psDB`
--

-- --------------------------------------------------------

--
-- 表的结构 `chapter`
--

CREATE TABLE `chapter` (
  `chapter_id` int(11) NOT NULL COMMENT '章节id',
  `course_id` int(11) NOT NULL COMMENT '课程id',
  `chapter_name` varchar(20) NOT NULL COMMENT '章节名称',
  `chapter_num` int(2) NOT NULL COMMENT '章节序号'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='章节表';

-- --------------------------------------------------------

--
-- 表的结构 `course`
--

CREATE TABLE `course` (
  `course_id` int(11) NOT NULL COMMENT '课程id',
  `course_name` varchar(20) NOT NULL COMMENT '课程名',
  `teacher` varchar(5) NOT NULL COMMENT '任课教师',
  `time` varchar(20) NOT NULL COMMENT '课程时间',
  `compile` enum('gcc','g++','python') NOT NULL COMMENT '编译语言',
  `type` enum('study','homework','both') NOT NULL COMMENT '学习/作业',
  `image` text NOT NULL COMMENT '课程图标',
  `announcement` varchar(100) NOT NULL DEFAULT '暂无' COMMENT '课程公告'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='课程表';

-- --------------------------------------------------------

--
-- 表的结构 `knowledge`
--

CREATE TABLE `knowledge` (
  `kid` int(11) NOT NULL COMMENT '知识点id',
  `chapter_id` int(11) NOT NULL COMMENT '章节id',
  `knowledge` varchar(50) NOT NULL COMMENT '知识点内容',
  `know_num` int(11) NOT NULL COMMENT '知识点序号',
  `difficulty` enum('1','2','3','4','5') NOT NULL COMMENT '困难度'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='知识点表';

-- --------------------------------------------------------

--
-- 表的结构 `load_log`
--

CREATE TABLE `load_log` (
  `id` int(11) NOT NULL COMMENT '登录id',
  `userid` int(11) NOT NULL COMMENT '学生id',
  `username` varchar(20) NOT NULL COMMENT '学生学号',
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '尝试登录时间',
  `state` enum('succes','fail') NOT NULL COMMENT '尝试结果'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='登录日志表';

-- --------------------------------------------------------

--
-- 表的结构 `question`
--

CREATE TABLE `question` (
  `qid` int(11) NOT NULL COMMENT '问题id',
  `chapter_id` int(11) NOT NULL COMMENT '章节id',
  `question_name` varchar(20) NOT NULL COMMENT '问题名称',
  `question` text NOT NULL COMMENT '问题内容',
  `input` text COMMENT '输入样例',
  `output` text NOT NULL COMMENT '输出样例',
  `template_top` text COMMENT '代码模版顶部',
  `template_bottom` text COMMENT '代码模版底部',
  `accept` int(11) NOT NULL DEFAULT '0' COMMENT '通过数量',
  `total` int(11) NOT NULL DEFAULT '0' COMMENT '提交数量',
  `acc_avg_row` double NOT NULL DEFAULT '0' COMMENT '通过的平均代码行数',
  `time` int(11) NOT NULL DEFAULT '1000' COMMENT '时间限制(ms)',
  `memory` int(11) NOT NULL DEFAULT '125' COMMENT '内存限制'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='问题表';

-- --------------------------------------------------------

--
-- 表的结构 `que_ans`
--

CREATE TABLE `que_ans` (
  `test_id` int(11) NOT NULL COMMENT '测试样例id',
  `qid` int(11) NOT NULL COMMENT '问题id',
  `input` text COMMENT '输入',
  `output` text NOT NULL COMMENT '输出'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='测试样例表';

-- --------------------------------------------------------

--
-- 表的结构 `que_know`
--

CREATE TABLE `que_know` (
  `id` int(11) NOT NULL COMMENT '问题-知识点id',
  `qid` int(11) NOT NULL COMMENT '问题id',
  `kid` int(11) NOT NULL COMMENT '知识点id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='问题知识点表';

-- --------------------------------------------------------

--
-- 表的结构 `section`
--

CREATE TABLE `section` (
  `section_id` int(11) NOT NULL COMMENT '课节id',
  `chapter_id` int(11) NOT NULL COMMENT '章节id',
  `section_name` varchar(30) NOT NULL COMMENT '课节名称',
  `section_num` int(11) NOT NULL COMMENT '课节序号',
  `section_info` text NOT NULL COMMENT '课节信息',
  `audio` text COMMENT '课节音频'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='课节表';

-- --------------------------------------------------------

--
-- 表的结构 `sec_pro`
--

CREATE TABLE `sec_pro` (
  `id` int(11) NOT NULL COMMENT '课节-程序id',
  `section_id` int(11) NOT NULL COMMENT '课节id',
  `time` int(11) NOT NULL COMMENT '显示时间（相对音频，单位秒）',
  `program` text NOT NULL COMMENT '显示的代码'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='课节程序表';

-- --------------------------------------------------------

--
-- 表的结构 `setting`
--

CREATE TABLE `setting` (
  `id` int(1) NOT NULL COMMENT 'id',
  `course_id` int(11) NOT NULL COMMENT '课程id',
  `save_max_num` int(11) NOT NULL DEFAULT '5' COMMENT '每个学生的每个问题保存的最大数量',
  `least_num` int(3) NOT NULL DEFAULT '3' COMMENT '每个簇最少做题的数量',
  `most_know_num` int(2) NOT NULL DEFAULT '3' COMMENT '问题所包含的最多的知识点数量',
  `homework_score_proportion` float NOT NULL DEFAULT '0.7' COMMENT '作业课程分数占比（小于1）',
  `max_recommend_num` int(11) NOT NULL DEFAULT '5' COMMENT '最大的推荐数量',
  `offset` float NOT NULL DEFAULT '0.01' COMMENT '做题数和通过数为0时的补偿量',
  `weight_done_num` float NOT NULL DEFAULT '1' COMMENT '做题数量权重',
  `weight_accept` float NOT NULL DEFAULT '1' COMMENT '通过率权重',
  `weight_difficulty` float NOT NULL DEFAULT '1' COMMENT '困难度权重',
  `study_timelimit` int(11) NOT NULL DEFAULT '1000' COMMENT '学习系统用户程序时间限制(ms)',
  `study_memorylimit` int(11) NOT NULL DEFAULT '125' COMMENT '学习系统用户内存限制(Mb)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='重要参数表';

-- --------------------------------------------------------

--
-- 表的结构 `stu_chapter`
--

CREATE TABLE `stu_chapter` (
  `id` int(11) NOT NULL COMMENT '学生-章节id',
  `userid` int(11) NOT NULL COMMENT '学生id',
  `chapter_id` int(11) NOT NULL COMMENT '章节id',
  `state` enum('true','false') NOT NULL DEFAULT 'false' COMMENT '通过状态'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='学生章节表';

-- --------------------------------------------------------

--
-- 表的结构 `stu_course`
--

CREATE TABLE `stu_course` (
  `id` int(11) NOT NULL COMMENT '选课id',
  `userid` int(11) NOT NULL COMMENT '学生id',
  `course_id` int(11) NOT NULL COMMENT '课程id',
  `score` int(11) NOT NULL DEFAULT '0' COMMENT '学生分数'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='学生选课表';

-- --------------------------------------------------------

--
-- 表的结构 `stu_que`
--

CREATE TABLE `stu_que` (
  `id` int(11) NOT NULL COMMENT '提交id',
  `userid` int(11) NOT NULL COMMENT '学生id',
  `qid` int(11) NOT NULL COMMENT '问题id',
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '提交时间',
  `state` enum('0','1','2','3','4','5') NOT NULL DEFAULT '0' COMMENT '当前状态',
  `score` int(3) NOT NULL DEFAULT '0' COMMENT '分数',
  `answer` text NOT NULL COMMENT '学生代码',
  `row` int(5) NOT NULL COMMENT '代码行数',
  `compiler` varchar(15) NOT NULL COMMENT '编译器',
  `result` text COMMENT '编译结果'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='学生答题表';

-- --------------------------------------------------------

--
-- 表的结构 `stu_section`
--

CREATE TABLE `stu_section` (
  `id` int(11) NOT NULL COMMENT '学生-课节id',
  `userid` int(11) NOT NULL COMMENT '学生id',
  `section_id` int(11) NOT NULL COMMENT '课节id',
  `time` int(11) NOT NULL DEFAULT '0' COMMENT '累计时长',
  `accept` enum('true','false') NOT NULL DEFAULT 'false' COMMENT '是否完成',
  `code` text COMMENT '学生代码',
  `judge` enum('true','false') NOT NULL DEFAULT 'false' COMMENT '是否需要判定',
  `state` enum('1','2','3','4','5') NOT NULL DEFAULT '1' COMMENT '程序状态',
  `result` text COMMENT '编译信息',
  `compiler` varchar(20) DEFAULT NULL COMMENT '编译器',
  `input` text COMMENT '输入',
  `output` text COMMENT '输出',
  `timelimit` int(11) DEFAULT NULL COMMENT '时间复杂度',
  `memory` int(11) DEFAULT NULL COMMENT '空间复杂度'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='学生课节表';

-- --------------------------------------------------------

--
-- 表的结构 `stu_test`
--

CREATE TABLE `stu_test` (
  `id` int(11) NOT NULL,
  `userid` int(11) NOT NULL COMMENT '学生id',
  `test_id` int(11) NOT NULL COMMENT '测试样例id',
  `state` enum('Waiting','Unknown','Memory Exceeded','Time Exceeded','Runtime Error','System Error','Accepted','Wrong Answer','Presentation Error') NOT NULL DEFAULT 'Waiting' COMMENT '状态',
  `time` int(11) DEFAULT NULL COMMENT '时间复杂度',
  `memory` int(11) DEFAULT NULL COMMENT '空间复杂度'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='学生测试样例表';

-- --------------------------------------------------------

--
-- 表的结构 `user_info`
--

CREATE TABLE `user_info` (
  `userid` int(11) NOT NULL COMMENT '学生id，唯一',
  `username` varchar(20) NOT NULL COMMENT '学生学号',
  `password` varchar(50) NOT NULL DEFAULT '0' COMMENT '学生密码',
  `authority` enum('root','common') NOT NULL DEFAULT 'common' COMMENT '管理员和普通用户',
  `recent_load` timestamp NULL DEFAULT NULL COMMENT '最近的登录时间',
  `state` enum('off','on','frozen','unregistered') NOT NULL DEFAULT 'unregistered' COMMENT '账号状态',
  `security` int(2) NOT NULL DEFAULT '3' COMMENT '账号的安全级，0时锁定',
  `class` varchar(20) DEFAULT NULL COMMENT '学生班级',
  `realname` varchar(10) NOT NULL COMMENT '学生姓名',
  `question` varchar(50) DEFAULT NULL COMMENT '学生密保问题',
  `answer` varchar(50) DEFAULT NULL COMMENT '学生密保答案',
  `telnumber` varchar(15) DEFAULT NULL COMMENT '学生联系电话'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户信息表';

--
-- 转存表中的数据 `user_info`
--

INSERT INTO `user_info` (`userid`, `username`, `password`, `authority`, `recent_load`, `state`, `security`, `class`, `realname`, `question`, `answer`, `telnumber`) VALUES
(1, 'adminadmin', '0', 'root', NULL, 'unregistered', 3, NULL, '管理员', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- 表的结构 `user_problem`
--

CREATE TABLE `user_problem` (
  `id` int(11) NOT NULL COMMENT '反馈问题id',
  `username` varchar(20) NOT NULL COMMENT '学生学号',
  `problem` text NOT NULL COMMENT '学生反馈的问题',
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '学生反馈时间',
  `new` enum('true','false') NOT NULL DEFAULT 'true' COMMENT '是否新反馈'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='反馈问题表';

--
-- 转储表的索引
--

--
-- 表的索引 `chapter`
--
ALTER TABLE `chapter`
  ADD PRIMARY KEY (`chapter_id`);

--
-- 表的索引 `course`
--
ALTER TABLE `course`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_name` (`course_name`);

--
-- 表的索引 `knowledge`
--
ALTER TABLE `knowledge`
  ADD PRIMARY KEY (`kid`);

--
-- 表的索引 `load_log`
--
ALTER TABLE `load_log`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `question`
--
ALTER TABLE `question`
  ADD PRIMARY KEY (`qid`);

--
-- 表的索引 `que_ans`
--
ALTER TABLE `que_ans`
  ADD PRIMARY KEY (`test_id`);

--
-- 表的索引 `que_know`
--
ALTER TABLE `que_know`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `section`
--
ALTER TABLE `section`
  ADD PRIMARY KEY (`section_id`);

--
-- 表的索引 `sec_pro`
--
ALTER TABLE `sec_pro`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `setting`
--
ALTER TABLE `setting`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_id` (`course_id`);

--
-- 表的索引 `stu_chapter`
--
ALTER TABLE `stu_chapter`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `stu_course`
--
ALTER TABLE `stu_course`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `stu_que`
--
ALTER TABLE `stu_que`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `stu_section`
--
ALTER TABLE `stu_section`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `stu_test`
--
ALTER TABLE `stu_test`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `user_info`
--
ALTER TABLE `user_info`
  ADD PRIMARY KEY (`userid`),
  ADD UNIQUE KEY `username` (`username`);

--
-- 表的索引 `user_problem`
--
ALTER TABLE `user_problem`
  ADD PRIMARY KEY (`id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `chapter`
--
ALTER TABLE `chapter`
  MODIFY `chapter_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '章节id';

--
-- 使用表AUTO_INCREMENT `course`
--
ALTER TABLE `course`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '课程id';

--
-- 使用表AUTO_INCREMENT `knowledge`
--
ALTER TABLE `knowledge`
  MODIFY `kid` int(11) NOT NULL AUTO_INCREMENT COMMENT '知识点id';

--
-- 使用表AUTO_INCREMENT `load_log`
--
ALTER TABLE `load_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '登录id';

--
-- 使用表AUTO_INCREMENT `question`
--
ALTER TABLE `question`
  MODIFY `qid` int(11) NOT NULL AUTO_INCREMENT COMMENT '问题id';

--
-- 使用表AUTO_INCREMENT `que_ans`
--
ALTER TABLE `que_ans`
  MODIFY `test_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '测试样例id';

--
-- 使用表AUTO_INCREMENT `que_know`
--
ALTER TABLE `que_know`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '问题-知识点id';

--
-- 使用表AUTO_INCREMENT `section`
--
ALTER TABLE `section`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '课节id';

--
-- 使用表AUTO_INCREMENT `sec_pro`
--
ALTER TABLE `sec_pro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '课节-程序id';

--
-- 使用表AUTO_INCREMENT `setting`
--
ALTER TABLE `setting`
  MODIFY `id` int(1) NOT NULL AUTO_INCREMENT COMMENT 'id';

--
-- 使用表AUTO_INCREMENT `stu_chapter`
--
ALTER TABLE `stu_chapter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '学生-章节id';

--
-- 使用表AUTO_INCREMENT `stu_course`
--
ALTER TABLE `stu_course`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '选课id';

--
-- 使用表AUTO_INCREMENT `stu_que`
--
ALTER TABLE `stu_que`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '提交id';

--
-- 使用表AUTO_INCREMENT `stu_section`
--
ALTER TABLE `stu_section`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '学生-课节id';

--
-- 使用表AUTO_INCREMENT `stu_test`
--
ALTER TABLE `stu_test`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `user_info`
--
ALTER TABLE `user_info`
  MODIFY `userid` int(11) NOT NULL AUTO_INCREMENT COMMENT '学生id，唯一', AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `user_problem`
--
ALTER TABLE `user_problem`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '反馈问题id';
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
