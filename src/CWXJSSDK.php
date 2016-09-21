<?php

namespace dekuan\xssharesdk;


use dekuan\vdata\CConst;
use dekuan\vdata\CRequest;
use dekuan\delib\CLib;


/**
 *	class of CWXJSSDK
 */
class CWXJSSDK
{
	const ERRORID_WXJSSDK_APP_ID				= CConst::ERROR_USER_START + 10;
	const ERRORID_WXJSSDK_APP_SECRET			= CConst::ERROR_USER_START + 15;

	const ERRORID_WXJSSDK_CB_PERSISTENT_TICKET		= CConst::ERROR_USER_START + 100;
	const ERRORID_WXJSSDK_CB_PERSISTENT_TOKEN		= CConst::ERROR_USER_START + 101;

	const ERRORID_WXJSSDK_JSAPITICKET			= CConst::ERROR_USER_START + 201;
	const ERRORID_WXJSSDK_SELF_URL				= CConst::ERROR_USER_START + 205;
	const ERRORID_WXJSSDK_NONCE_STR				= CConst::ERROR_USER_START + 210;
	const ERRORID_WXJSSDK_SIGNATURE				= CConst::ERROR_USER_START + 215;
	const ERRORID_WXJSSDK_TICKET				= CConst::ERROR_USER_START + 220;
	const ERRORID_WXJSSDK_TOKEN				= CConst::ERROR_USER_START + 225;

	const ERRORID_WXJSSDK_GET_JSAPITICKET_FROM_SERVER	= CConst::ERROR_USER_START + 300;
	const ERRORID_WXJSSDK_GET_ACCESSTOKEN_FROM_SERVER	= CConst::ERROR_USER_START + 305;


	//	...
	protected static $g_cStaticInstance;

	private $m_sAppId;
	private $m_sAppSecret;

	public function __construct()
	{
		$this->m_sAppId		= '';
		$this->m_sAppSecret	= '';
	}
	static function GetInstance()
	{
		if ( is_null( self::$g_cStaticInstance ) || ! isset( self::$g_cStaticInstance ) )
		{
			self::$g_cStaticInstance = new self();
		}
		return self::$g_cStaticInstance;
	}


	public function SetConfig( $sAppId, $sAppSecret )
	{
		if ( ( ! is_string( $sAppId ) && ! is_numeric( $sAppId ) ) || 0 == strlen( $sAppId ) )
		{
			return self::ERRORID_WXJSSDK_APP_ID;
		}
		if ( ! is_string( $sAppSecret ) && ! is_numeric( $sAppSecret ) )
		{
			return self::ERRORID_WXJSSDK_APP_SECRET;
		}

		$this->m_sAppId		= $sAppId;
		$this->m_sAppSecret	= $sAppSecret;

		//	...
		return CConst::ERROR_SUCCESS;
	}

	public function GetSignData( Array $arrPersistentTicket, Array $arrPersistentToken, & $arrDataReturn = null )
	{
		//
		//	arrPersistentTicket	- [in] array	callable function addresses to get/save persistent ticket value
		//				[
		//					'get'	=> callable function address to get ...
		//					'save'	=> callable function address to save ...
		//				]
		//	arrPersistentToken	- [in] array	callable function addresses to get/save persistent token value
		//				[
		//					'get'	=> callable function address to get ...
		//					'save'	=> callable function address to save ...
		//				]
		//	RETURN			- error id
		//
		$nRet	= CConst::ERROR_UNKNOWN;

		$sJsApiTicket	= '';
		$nErrorId	= $this->_GetJsApiTicket( $arrPersistentTicket, $arrPersistentToken, $sJsApiTicket );
		if ( CConst::ERROR_SUCCESS == $nErrorId )
		{
			$sUrl		= $this->_GetSelfUrl();
			$nTimestamp	= time();
			$sNonceStr	= $this->_GetNonceStr();
			$sSignature	= $this->_GetSign( $sJsApiTicket, $sNonceStr, $nTimestamp, $sUrl );

			if ( CLib::IsExistingString( $sSignature ) )
			{
				$nRet = CConst::ERROR_SUCCESS;
				$arrDataReturn	=
					[
						'appid'		=> $this->m_sAppId,
						'noncestr'	=> $sNonceStr,
						'timestamp'	=> $nTimestamp,
						'url'		=> $sUrl,
						'signature'	=> $sSignature,
					];
			}
			else
			{
				$nRet = self::ERRORID_WXJSSDK_SIGNATURE;
			}
		}
		else
		{
			$nRet = $nErrorId;
		}

		return $nRet;
	}


	////////////////////////////////////////////////////////////////////////////////
	//	private
	//

	private function _GetJsApiTicket( Array $arrPersistentTicket, Array $arrPersistentToken, & $sTicketReturn = '' )
	{
		//
		//	arrPersistentTicket	- [in] array	callable function addresses to get/save persistent ticket value
		//				[
		//					'get'	=> callable function address to get ...
		//					'save'	=> callable function address to save ...
		//				]
		//	pfnPersistentToken	- [in] array	callable function addresses to get/save persistent token value
		//				[
		//					'get'	=> callable function address to get ...
		//					'save'	=> callable function address to save ...
		//				]
		//	RETURN			- ticket
		//
		//	NOTE
		//	jsapi_ticket 的有效期为 7200 秒
		//
		$nRet = CConst::ERROR_UNKNOWN;

		$sTicketReturn	= '';
		$sPValue	= '';

		if ( ! CLib::IsArrayWithKeys( $arrPersistentTicket, [ 'get', 'save' ] ) )
		{
			return self::ERRORID_WXJSSDK_CB_PERSISTENT_TICKET;
		}
		if ( ! CLib::IsArrayWithKeys( $arrPersistentToken, [ 'get', 'save' ] ) )
		{
			return self::ERRORID_WXJSSDK_CB_PERSISTENT_TOKEN;
		}

		//
		//	try to get ticket from persistent storage
		//
		if ( is_callable( $arrPersistentTicket['get'] ) )
		{
			$sPValue = $arrPersistentTicket['get']();
			if ( ! is_string( $sPValue ) && ! is_numeric( $sPValue ) )
			{
				$sPValue = '';
			}
		}

		if ( strlen( $sPValue ) > 0 )
		{
			//
			//	pick up the value from persistent storage
			//
			$nRet = CConst::ERROR_SUCCESS;
			$sTicketReturn = $sPValue;
		}
		else
		{
			//
			//	obtain a fresh ticket via a RPC call
			//
			$sTicket	= '';
			$nErrorId	= $this->_GetJsApiTicketFromServer( $arrPersistentToken, $sTicket );
			if ( CConst::ERROR_SUCCESS == $nErrorId )
			{
				if ( is_string( $sTicket ) || is_numeric( $sTicket ) )
				{
					if ( strlen( $sTicket ) > 0 )
					{
						//
						//	successfully
						//
						$nRet = CConst::ERROR_SUCCESS;
						$sTicketReturn = $sTicket;

						//
						//	save the ticket to persistent storage
						//
						if ( is_callable( $arrPersistentTicket['save'] ) )
						{
							$arrPersistentTicket['save']( $sTicket );
						}
					}
					else
					{
						$nRet = self::ERRORID_WXJSSDK_GET_JSAPITICKET_FROM_SERVER;
					}
				}
				else
				{
					$nRet = self::ERRORID_WXJSSDK_GET_JSAPITICKET_FROM_SERVER;
				}
			}
			else
			{
				$nRet = $nErrorId;
			}
		}

		return $nRet;
	}
	private function _GetJsApiTicketFromServer( Array $arrPersistentToken, & $sTicketReturn = '' )
	{
		//
		//	arrPersistentToken	- [in] array	callable function addresses to get/save persistent token value
		//				[
		//					'get'	=> callable function address to get ...
		//					'save'	=> callable function address to save ...
		//				]
		//	sTicketReturn		- [out] string	ticket write back to caller
		//	RETURN		- ticket
		//
		//	NOTE
		//	jsapi_ticket 的有效期为 7200 秒
		//
		//	Response from the server of wechat:
		//	{"errcode":40001,"errmsg":"invalid credential, access_token is invalid or not latest hint: [qQyUCA0449vr22]"}
		//
		$nRet = CConst::ERROR_UNKNOWN;
		$sTicketReturn	= '';

		if ( ! CLib::IsArrayWithKeys( $arrPersistentToken, [ 'get', 'save' ] ) )
		{
			return self::ERRORID_WXJSSDK_CB_PERSISTENT_TOKEN;
		}

		//
		//	obtain a fresh ticket via a RPC call
		//
		$sAccessToken	= '';
		$nErrorId	= $this->_GetAccessToken( $arrPersistentToken, $sAccessToken );
		if ( CConst::ERROR_SUCCESS == $nErrorId)
		{
			if ( is_string( $sAccessToken ) || is_numeric( $sAccessToken ) )
			{
				$sUrl = sprintf
				(
					"https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=%s&access_token=%s",
					"jsapi",
					$sAccessToken
				);
				$arrRes = @ json_decode( $this->_HttpGet( $sUrl ), true );
				if ( is_array( $arrRes ) && array_key_exists( 'ticket', $arrRes ) )
				{
					$sTicket = $arrRes[ 'ticket' ];
					if ( is_string( $sTicket ) || is_numeric( $sTicket ) )
					{
						if ( strlen( $sTicket ) > 0 )
						{
							$nRet = CConst::ERROR_SUCCESS;
							$sTicketReturn = $sTicket;
						}
						else
						{
							$nRet = self::ERRORID_WXJSSDK_GET_JSAPITICKET_FROM_SERVER;
						}
					}
					else
					{
						$nRet = self::ERRORID_WXJSSDK_GET_JSAPITICKET_FROM_SERVER;
					}
				}
				else
				{
					$nRet = self::ERRORID_WXJSSDK_GET_JSAPITICKET_FROM_SERVER;
				}
			}
			else
			{
				$nRet = self::ERRORID_WXJSSDK_TICKET;
			}
		}
		else
		{
			$nRet = self::ERRORID_WXJSSDK_TICKET;
		}

		return $nRet;
	}


	private function _GetAccessToken( Array $arrPersistentToken, & $sAccessTokenReturn = '' )
	{
		//
		//	arrPersistentSave	- [in] callable function address to save persistent value
		//	RETURN			- ticket
		//
		//	NOTE
		//	jsapi_ticket 的有效期为 7200 秒
		//
		if ( ! CLib::IsArrayWithKeys( $arrPersistentToken, [ 'get', 'save' ] ) )
		{
			return self::ERRORID_WXJSSDK_CB_PERSISTENT_TOKEN;
		}

		//	...
		$nRet		= CConst::ERROR_UNKNOWN;

		$sAccessTokenReturn	= '';
		$sPValue		= '';

		//
		//	try to get ticket from persistent storage
		//
		if ( is_callable( $arrPersistentToken['get'] ) )
		{
			$sPValue = $arrPersistentToken['get']();
			if ( ! is_string( $sPValue ) && ! is_numeric( $sPValue ) )
			{
				$sPValue = '';
			}
		}

		if ( strlen( $sPValue ) > 0 )
		{
			//
			//	pick up the value from persistent storage
			//
			$nRet = CConst::ERROR_SUCCESS;
			$sAccessTokenReturn = $sPValue;
		}
		else
		{
			//
			//	obtain a fresh ticket via a RPC call
			//
			$sAccessToken = $this->_GetAccessTokenFromServer();
			if ( is_string( $sAccessToken ) || is_numeric( $sAccessToken ) )
			{
				if ( strlen( $sAccessToken ) > 0 )
				{
					$nRet = CConst::ERROR_SUCCESS;
					$sAccessTokenReturn = $sAccessToken;

					//
					//	save the ticket to persistent storage
					//
					if ( is_callable( $arrPersistentToken['save'] ) )
					{
						$arrPersistentToken['save']( $sAccessToken );
					}
				}
				else
				{
					$nRet = self::ERRORID_WXJSSDK_GET_ACCESSTOKEN_FROM_SERVER;
				}
			}
			else
			{
				$nRet = self::ERRORID_WXJSSDK_GET_ACCESSTOKEN_FROM_SERVER;
			}
		}

		return $nRet;
	}


	private function _GetAccessTokenFromServer()
	{
		//
		//	Response from the server of wechat
		//	{"errcode":41002,"errmsg":"appid missing hint: [_lq7ja0449vr20]"}
		//
		//
		$sRet	= '';

		//	...
		$sUrl = sprintf
		(
			"https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s",
			$this->m_sAppId,
			$this->m_sAppSecret
		);
		$sContent	= $this->_HttpGet( $sUrl );
		$arrRes		= @ json_decode( $sContent, true );
		if ( is_array( $arrRes ) && array_key_exists( 'access_token', $arrRes ) )
		{
			$sAccessToken	= $arrRes[ 'access_token' ];
			if ( is_string( $sAccessToken ) || is_numeric( $sAccessToken ) )
			{
				if ( strlen( $sAccessToken ) > 0 )
				{
					//	successfully
					$sRet = $sAccessToken;
				}
			}
		}

		return $sRet;
	}


	private function _GetSign( $sJsApiTicket, $sNonceStr, $nTimestamp, $sUrl )
	{
		if ( ! is_string( $sJsApiTicket ) && ! is_numeric( $sJsApiTicket ) )
		{
			return '';
		}
		if ( ! is_string( $sNonceStr ) && ! is_numeric( $sNonceStr ) )
		{
			return '';
		}
		if ( ! is_string( $nTimestamp ) && ! is_numeric( $nTimestamp ) )
		{
			return '';
		}
		if ( ! CLib::IsExistingString( $sUrl ) )
		{
			return '';
		}

		//
		//	这里参数的顺序要按照 key 值 ASCII 码升序排序
		//
		$sString = sprintf
		(
			"jsapi_ticket=%s&noncestr=%s&timestamp=%s&url=%s",
			$sJsApiTicket, $sNonceStr, $nTimestamp, $sUrl
		);

		return sha1( $sString );
	}
	private function _GetSelfUrl()
	{
		$sHost	= '';
		$sUri	= '';

		if ( is_array( $_SERVER ) && array_key_exists( 'HTTP_HOST', $_SERVER ) )
		{
			$sHost	= $_SERVER[ 'HTTP_HOST' ];
		}
		if ( is_array( $_SERVER ) && array_key_exists( 'REQUEST_URI', $_SERVER ) )
		{
			$sUri	= $_SERVER[ 'REQUEST_URI' ];
		}
		$sUrlHeader = $this->_IsHttps() ? 'https' : 'http';

		return sprintf( "%s://%s%s", $sUrlHeader, $sHost, $sUri );
	}

	private function _GetNonceStr( $nLength = 16 )
	{
		if ( ! is_numeric( $nLength ) )
		{
			return '';
		}

		$sRet	= '';
		$sChars	= 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

		for ( $i = 0; $i < $nLength; $i ++ )
		{
			$sRet .= substr( $sChars, mt_rand( 0, strlen( $sChars ) - 1 ), 1 );
		}

		return $sRet;
	}

	private function _HttpGet( $sUrl, $nTimeout = 5 )
	{
		if ( ! is_string( $sUrl ) || 0 == strlen( $sUrl ) )
		{
			return '';
		}
		if ( ! is_numeric( $nTimeout ) )
		{
			return '';
		}

		$sRet		= '';
		$cRequest	= CRequest::GetInstance();
		$arrResponse	= [];
		$nErrorId	= $cRequest->HttpRaw
		(
			[
				'method'	=> 'GET',
				'url'		=> $sUrl,
				'data'		=> [],
				'version'	=> '',
				'timeout'	=> $nTimeout,
			],
			$arrResponse
		);

		if ( CConst::ERROR_SUCCESS == $nErrorId &&
			$cRequest->IsValidRawResponse( $arrResponse ) )
		{
			$sRet = $arrResponse[ 'data' ];
		}

		return $sRet;
	}
	private  function _IsHttps()
	{
		$bRet	= false;

		if ( is_array( $_SERVER ) )
		{
			if ( array_key_exists( 'HTTPS', $_SERVER ) &&
				is_string( $_SERVER[ 'HTTPS' ] ) &&
				strlen( $_SERVER[ 'HTTPS' ] ) > 0 &&
				0 == strcasecmp( 'on', $_SERVER[ 'HTTPS' ] ) )
			{
				$bRet = true;
			}
			else if ( array_key_exists( 'HTTP_X_CLIENT_SCHEME', $_SERVER ) &&
				is_string( $_SERVER[ 'HTTP_X_CLIENT_SCHEME' ] ) &&
				strlen( $_SERVER[ 'HTTP_X_CLIENT_SCHEME' ] ) > 0 &&
				0 == strcasecmp( 'https', $_SERVER[ 'HTTP_X_CLIENT_SCHEME' ] ) )
			{
				$bRet = true;
			}
		}

		return $bRet;
	}
}