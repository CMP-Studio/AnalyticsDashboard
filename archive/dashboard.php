<?php

require_once "analytics.php";
require_once "highcharts.php";
require_once "cache.php";


function getAccounts()
{
	$accounts = array();
	$accounts["CMOA"] = "ga:53193816";
	$accounts["CSC"] = "ga:12575410";
	$accounts["CMNH"] = "ga:19917087"; //"ga:86907168";
	$accounts["AWM"] = "ga:30663551";
	$accounts["CMP"] = "ga:43375288";
	
	return $accounts;
}

function GDate($time = '')
{
	if($time == '')	return date('Y-m-d');
	return date('Y-m-d', $time);
}

function fromGDateHour($gTime)
{
	$year = substr($gTime,0,4);
	$mon = substr($gTime, 4, 2);
	$day = substr($gTime, 6,2);
	$hr = substr($gTime, 8,2);
	
	return $year . "-" . $mon . "-" . $day . " " . $hr . ":00";
}
function getTotalData($analytics, $from = '', $to = '', $cache=true)
{
	
	$accounts = getAccounts();
	$data = array();
	foreach($accounts as $aname=>$aval)
	{
		$data[$aname] = getData($analytics,$aval,$from,$to,$cache);
	}
	return $data;
	
}
function getCompData($analytics, $from = '', $to = '', $cache=true)
{
	if($to == '') $to = GDate();
	
	if($from == '')
		$from = GDate(strtotime( '-3 year'));
	
	
	//check cache for dataset
	if($cache)
	{
		$cData = loadFromCache("COMP" . "@" . $from . "@" . $to);
		if(!empty($cData)) return unserialize ( $cData );
	}
	
	$data = array();
	$data["hist"] = array();
	$acct = getAccounts();
	foreach($acct as $aname=>$aval)
	{
		

		try
		{
			$data["hist"][$aname] = invertData(runQuery($analytics, $aval,$from,$to,"ga:pageviews","ga:date","",'10000')->getRows());
		}
		catch (Exception $e)	
		{}
		
		try
		{
			$data["duration"][$aname] = invertData(runQuery($analytics, $aval,$from,$to,"ga:avgSessionDuration","ga:date","",'10000')->getRows());
		}
		catch (Exception $e)	{ 
			
		}
		
		try
		{
			$data["users"][$aname] = invertData(runQuery($analytics, $aval,$from,$to,"ga:newUsers","ga:date","",'10000')->getRows());
		}
		catch (Exception $e)	
		{ }

	}
	//invertData();

		$cData = serialize ($data);
		storeInCache("COMP" . "@" . $from . "@" . $to, $cData);
	
	
	return $data;

}

function getMuseum()
{
	$accounts = getAccounts();
	
	//Set account to use
	if(isset($_GET["m"]))
	{
		if($_GET["m"] == "Compare") return "Compare";
		if($_GET["m"] == "Combined") return "Combined";
		$account = $accounts[$_GET["m"]];
	}
	else
	{
		return "Combined";
	}
	
	return $account;
}

function getData($analytics, $account =  '', $from = "", $to = "", $ltfrom = "", $cache=true)
{
	//$to = '2015-03-10';
	if ($account == '')
		$account = getMuseum();
		
	if($from == '')
		$from = GDate(strtotime( '-1 month'));
		
	if($ltfrom == '')
		$ltfrom = GDate(strtotime( '-3 years'));
	
	if($to == '')
		$to = GDate();
	
	$data = array();
	//check cache for dataset
	if($cache)
	{
		$cData = loadFromCache($account . "@" . $from . "@" . $to);
		if(!empty($cData)) return unserialize ( $cData );
	}
	
	
	//all queries should be in try catch blocks
	
	//Mobile OS data
	try
	{
		$data["mobile-os"] = invertData(runQuery($analytics, $account,$from,$to,"ga:users","ga:operatingSystem","-ga:users",'5','','gaid::-11')->getRows());
	}
	catch (Exception $e)	{	}
	//web traffic
	try
	{
		$data["web-traffic"] = invertData(runQuery($analytics, $account,$from,$to,"ga:pageviews,ga:visits,ga:users","ga:date")->getRows());
	}
	catch (Exception $e)	{	}
	
	try
	{
		$data["web-byhour"] = invertData(runQuery($analytics, $account,$from,$to,"ga:pageviews,ga:visits,ga:users","ga:hour")->getRows());
	}
	catch (Exception $e)	{	}
	
	try
	{
		$data["web-browser"] = invertData(runQuery($analytics, $account,$from,$to,"ga:users","ga:browser","-ga:users",'5','ga:deviceCategory==desktop')->getRows());
	}
	catch (Exception $e)	{	}
	
	try
	{
		$data["most-viewed"] =invertData( runQuery($analytics, $account,$from,$to,"ga:pageviews","ga:pagePath","-ga:pageviews",'15')->getRows());
	}
	catch (Exception $e)	{	}
		try
	{
		$data["hist-views"] = invertData(runQuery($analytics, $account,$ltfrom,$to,"ga:pageviews","ga:date","",'10000')->getRows());
	}
	catch (Exception $e)	{	}
		try
	{
		$data["tos"] = invertData(runQuery($analytics, $account,$from,$to,"ga:avgTimeOnPage,ga:avgSessionDuration","ga:date","",'10000')->getRows());
	}
	catch (Exception $e)	{	}
	
	
	

		$cData = serialize ($data);
		storeInCache($account . "@" . $from . "@" . $to, $cData);
	
	return $data;
}

function getCharts($analytics)
{
$charts = array();
$colors = getColorScheme();


if(getMuseum() == "Compare")
{
	$data = getCompData($analytics);
	//print_r($data);
	//Compare Views
	$chart = new Highstock();
	$c = 0;
	$hist = $data["hist"];

	foreach($hist as $dname=>$dval)
	{
		$chart->addSeries($dval[0],$dval[1],$dname,$colors[$c]);
		$c++;
	}
	$chart->addLegend();
	$charts["hist-views"] = $chart->toChart("#hist");

	//Compare Duration
	$chart = new Highstock();
	$c = 0;
	$cData = $data["duration"];

	foreach($cData as $dname=>$dval)
	{
		$chart->addSeries($dval[0],$dval[1],$dname,$colors[$c]);
		$c++;
	}
	$chart->addLegend();
	$charts["hist-dur"] = $chart->toChart("#duration");

	//Compare Users
	$chart = new Highstock();
	$c = 0;
	$hist = $data["users"];

	foreach($hist as $dname=>$dval)
	{
		$chart->addSeries($dval[0],$dval[1],$dname,$colors[$c]);
		$c++;
	}
	$chart->addLegend();
	$charts["hist-users"] = $chart->toChart("#users");
}
else if (getMuseum() == "Combined")
{
	$data = getTotalData($analytics);
	
	$chart = new Highchart('areaspline');
	$chart->addLegend();
	$chart->addPlotOption('fillOpacity',0.333);
	$chart->addPlotOption('stacking','normal');
	
	$first = true;
	$c = 0;
	foreach($data as $mname=>$mval)
	{
		$wt = $mval["web-traffic"];
		if($first)
		{
			$start = strtotime($wt[0][0]);
			$int = strtotime($wt[0][1]) - strtotime($wt[0][0]);
			$chart->addTimestamps($start*1000, $int*1000);
			$first = false;
		}
		$chart->addSeries($wt[1],$mname,$colors[$c]);
		$c++;
	}
	$charts["web-traffic"] = $chart->toChart("#views");
	
	
}
else
{
	$data = getData($analytics);
	
	



	//Overall web traffic

	if(isset($data["web-traffic"]))
	{
		$wt = $data["web-traffic"];

		$start = strtotime($wt[0][0]);
		$int = strtotime($wt[0][1]) - strtotime($wt[0][0]);

		$areaspline = new Highchart('areaspline');
		$areaspline->addLegend();
		$areaspline->addPlotOption('fillOpacity',0.2);
		$areaspline->addSeries($wt[1],'Pageviews',$colors[3]);
		$areaspline->addSeries($wt[2],'Sessions',$colors[2]);
		$areaspline->addSeries($wt[3],'Users',$colors[1]);
		$areaspline->addTimestamps($start*1000, $int*1000);

		$charts["web-traffic"] = $areaspline->toChart("#web-traffic");
	}

	if(isset($data["mobile-os"]))
	{
		$mos = $data["mobile-os"];
		
		$bar = new Highchart('bar');
		$bar->addCategories($mos[0]);
		$bar->addSeries($mos[1],'Users', $colors[1]);
		//$bar->addDrilldownSeries(array('1','2','3'), array(14,12,2), array('1'=>array('test1'=>6,'test2'=>8)), 'Drilldown', $colors[0]);
		$charts["mobile-os"] = $bar->toChart("#mobile-os");

	}

	if(isset($data["web-byhour"]))
	{
		$wt = $data["web-byhour"];

		$start = strtotime("12am");
		$int = strtotime("1 hour");

		$areaspline = new Highchart('areaspline');
		$areaspline->addLegend();
		$areaspline->addPlotOption('fillOpacity',0.2);
		$areaspline->addSeries($wt[1],'Pageviews',$colors[3]);
		$areaspline->addSeries($wt[2],'Sessions',$colors[2]);
		$areaspline->addSeries($wt[3],'Users',$colors[1]);
		$areaspline->addCategories(hours(), 3);
		$charts["web-byhour"] = $areaspline->toChart("#web-byhour");

	}

	if(isset($data["web-browser"]))
	{
		$wb = $data["web-browser"];
		
		$bar = new Highchart('bar');
		$bar->addCategories($wb[0]);
		$bar->addSeries($wb[1],'Users', $colors[0]);
		$charts["web-browser"] = $bar->toChart("#web-browser");

	}

	if(isset($data["most-viewed"]))
	{
		$id = $data["most-viewed"];
		
		
		
		$bar = new Highchart('bar');
		$bar->addCategories($id[0]);
		$bar->addSeries($id[1],'Views', $colors[2]);
		$charts["most-viewed"] = $bar->toChart("#most-viewed");

	}

	if(isset($data["tos"]))
	{
		$id = $data["tos"];
		
		$chart = new Highchart('areaspline');
		
		$start = strtotime(($id[0][0])) * 1000;
		$int = (strtotime(($id[0][1])) - strtotime(($id[0][0]))) * 1000;
		$chart->addTimestamps($start, $int);
		$chart->addLegend();
		//$chart->addPlotOption('fillOpacity',0.2);
		$chart->addSeries($id[2],"Avg. Time on Site (s)", $colors[1]);
		$chart->addSeries($id[1],"Avg. Time on Page (s)", $colors[0]);
		
		$charts["tos"] = $chart->toChart("#tos");
		
		
	}

	if(isset($data["hist-views"]))
	{
		$id = $data["hist-views"];
		
		
		
		$chart = new Highstock();
		$chart->addSeries($id[0], $id[1],'Views', $colors[3]);
		$charts["hist-views"] = $chart->toChart("#hist-views");

	}

}


return $charts;

}



function invertData($data)
{
	$newData = array();
	$cols = 0;
	foreach($data[0] as $d)
	{
		array_push($newData, array());
		$cols++;
	}
	$i = 0;
	foreach($data as $d)
	{
		for($j = 0; $j < $cols; $j++)
		{
			$newData[$j][$i] = $d[$j];
		
		}
		$i++;
	}
	return $newData;
}

function hours()
{

	$app = array("am","pm");
	$res = array();
	for($i = 0; $i < 2; $i++)
	{
		for($j = 0; $j < 12; $j++)
		{
			if($j == 0)
			{
				array_push($res,"12 " . $app[$i]);
			}
			else
			{
				array_push($res,$j . " " . $app[$i]);
			}
		}
	
	}
	return $res;
}


function setupCharts($cnames, $ctitles)
{
	$i = 0;
	foreach($cnames as $cname)
	{
		setupChart($cname, $ctitles[$i]);
		$i ++;
	}

}

function setupChart($cname, $ctitle)
{
?>

<div class="col-xs-12 col-md-6">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h3 class="panel-title"><?php print $ctitle;?></h3>
		</div>
		<div class="panel-body">

			<div class='chart-holder' id="<?php print $cname;?>">
			</div>

		</div>
	</div>
</div>

<?php
}

?>