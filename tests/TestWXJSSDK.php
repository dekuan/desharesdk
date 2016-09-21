<?php

error_reporting( E_ALL );


@ ini_set( 'date.timezone', 'UTC' );
@ date_default_timezone_set( 'UTC' );

@ ini_set( 'display_errors',		'on' );
@ ini_set( 'max_execution_time',	'60' );
@ ini_set( 'max_input_time',		'0' );
@ ini_set( 'memory_limit',		'512M' );


require_once __DIR__ . '/../src/CWXJSSDK.php';
require_once __DIR__ . '/../vendor/dekuan/vdata/src/CConst.php';
require_once __DIR__ . '/../vendor/dekuan/vdata/src/CCors.php';
require_once __DIR__ . '/../vendor/dekuan/vdata/src/CVData.php';
require_once __DIR__ . '/../vendor/dekuan/vdata/src/CRequest.php';
require_once __DIR__ . '/../vendor/dekuan/delib/src/CLib.php';



use dekuan\xssharesdk\CWXJSSDK;


class TestCWeChatJSSDK extends PHPUnit_Framework_TestCase
{
	public function testCWXJSSDK()
	{
		$cWXJSSDK	= CWXJSSDK::GetInstance();

		echo "\r\n";
		echo "--------------------------------------------------------------------------------";
		echo "\r\n";
		echo __CLASS__ . "::" . __FUNCTION__ . "\r\n";

		//
		//	...
		//
		$arrSignData	= [];
		$nErrorId	= $cWXJSSDK->SetConfig( 'wx00bfc9425c85fd5a', '0417564b4c93429b7f4c3a5444d3b646' );
		if ( \dekuan\vdata\CConst::ERROR_SUCCESS == $nErrorId )
		{
			$nErrorId	= $cWXJSSDK->GetSignData
			(
			//	ticket
				[
					'get'	=> function()
					{
						//	get ticket from persistent storage
						return '';
					},
					'save'	=> function( $sValue )
					{
						//	save ticket to persistent storage, if necessarily
						return true;
					},
				],

				//	Token
				[
					'get'	=> function()
					{
						//	get access token from persistent storage
						return '';
					},
					'save'	=> function( $sValue )
					{
						//	save access token to persistent storage, if necessarily
						return true;
					},
				],

				$arrSignData
			);

			echo "error id=$nErrorId\r\n";
			print_r( $arrSignData );
		}
		else
		{
			echo "error in SetConfig, error id=$nErrorId\r\n";
		}

		echo "\r\n";
	}


}
