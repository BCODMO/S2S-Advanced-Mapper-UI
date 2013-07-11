<?php

require_once("utils.php");

$box = null;
$begin = null;
$end = null;
$insts = null;
$instcats = null;
$ppl = null;
$prjs = null;
$progs = null;
$deps = null;
$awards = null;
$params = null;
$paramcats = null;
$platforms = null;
$limit = 10;
$offset = 0;
$sort = null;

if (@$_GET['bbox'] && @$_GET['bbox'] != '')
  $box = explode(";",$_GET['bbox']);
if (@$_GET['startDate'] && @$_GET['startDate'] != '')
  $begin = $_GET['startDate'];
if (@$_GET['endDate'] && @$_GET['endDate'] != '')
  $end = $_GET['endDate'];
if (@$_GET['instruments'] && @$_GET['instruments'] != '')
  $insts = explode(";",$_GET['instruments']);
if (@$_GET['people'] && @$_GET['people'] != '')
  $ppl = explode(";",$_GET['people']);
if (@$_GET['projects'] && @$_GET['projects'] != '')
  $prjs = explode(";",$_GET['projects']);
if (@$_GET['programs'] && @$_GET['programs'] != '')
  $progs = explode(";",$_GET['programs']);
if (@$_GET['deployments'] && @$_GET['deployments'] != '')
  $deps = explode(";",$_GET['deployments']);
if (@$_GET['awards'] && @$_GET['awards'] != '')
  $awards = explode(";",$_GET['awards']);
if (@$_GET['parameters'] && @$_GET['parameters'] != '')
  $params = explode(";",$_GET['parameters']);
if (@$_GET['instcats'] && @$_GET['instcats'] != '')
  $instcats = explode(";",$_GET['instcats']);
if (@$_GET['paramcats'] && @$_GET['paramcats'] != '')
  $paramcats = explode(";",$_GET['paramcats']);
if (@$_GET['platforms'] && @$_GET['platforms'] != '')
  $platforms = explode(";",$_GET['platforms']);
if (@$_GET['limit'] && @$_GET['limit'] != '')
  $limit = $_GET['limit'];
if (@$_GET['offset'] && @$_GET['offset'] != '')
  $offset = $_GET['offset'];
if (@$_GET['sort'] && @$_GET['sort'] != '')
  $sort = $_GET['sort'];
if (@$_GET['request'] && @$_GET['request'] != '')
  $type = $_GET['request'];

getResponse(@$box, @$begin, @$end, @$insts, @$instcats, @$ppl, @$prjs, @$progs, @$deps, @$awards, @$params, @$paramcats, @$platforms, @$type, @$limit, @$offset, @$sort);

?>