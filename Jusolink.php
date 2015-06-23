<?php
/**
* =====================================================================================
* Class for base module for Popbill API SDK. It include base functionality for
* RESTful web service request and parse json result. It uses Linkhub module
* to accomplish authentication APIs.
*
* This module uses curl and openssl for HTTPS Request. So related modules must
* be installed and enabled.
*
* http://www.linkhub.co.kr
* Author : Kim Seongjun (pallet027@gmail.com)
* Written : 2014-06-23
*
* Thanks for your interest.
* We welcome any suggestions, feedbacks, blames or anythings.
* ======================================================================================
*/

require_once 'Linkhub/linkhub.auth.php';
require_once 'Linkhub/JSON.php';

class Jusolink
{
	//생성자
    function Jusolink($LinkID,$SecretKey) {
    	$this->Linkhub = new Linkhub($LinkID,$SecretKey);
		$this->scopes[] = '200';
    	$this->VERS = '1.0';
    	$this->ServiceID = 'JUSOLINK';
    	$this->ServiceURL = 'https://juso.linkhub.co.kr';
    }
            
    function getsession_Token() {
    	$Refresh = true;

		if(!is_null($this->token)){
    		$Expiration = gmdate($this->token->expiration);
    		$now = gmdate("Y-m-d H:i:s",time());
    		$Refresh = $Expiration < $now; 
		}
    	
    	if($Refresh){
    		$this->token = $this->Linkhub->getToken($this->ServiceID, null, $this->scopes);
    		//TODO return Exception으로 처리 변경...

			if(is_a($this->token,'LinkhubException')) {
    			return new JusolinkException($this->token);
    		}
    	}
    	return $this->token->session_token;
    }

	//회원 잔여포인트 확인
    function GetBalance() {
    	$_Token = $this->getsession_Token(null);

    	if(is_a($_Token,'JusolinkException')) return $_Token;
    	
    	return $this->Linkhub->getPartnerBalance($_Token,$this->ServiceID);
    }

	// 검색단가 확인
	function GetUnitCost(){
    	$result = $this->executeCURL('/Search/UnitCost');
		
		return $result->unitCost;
	}

	// 주소 검색
	function search($IndexWord, $PageNum, $PerPage = null, $noSuggest = false, $noDiff = false){
		if(!is_null($PageNum) && $PageNum < 1) $PageNum = null;

		if($PerPage != null){
			if($PerPage < 0) $PerPage = 20;
		}

		$url = '/Search';

		if(is_null($IndexWord) || $IndexWord === ""){
			return new JusolinkException(-99999999, '검색어가 입력되지 않았습니다.');
		}
		
		$url = $url.'?Searches='.urlencode($IndexWord);

		if(!is_null($PageNum)){
			$url = $url.'&PageNum='.$PageNum;
		}

		if(!is_null($PerPage)){
			$url = $url.'&PerPage='.$PerPage;
		}

		if(!is_null($noSuggest) && $noSuggest){
			$url = $url.'&noSuggest=true';
		}	

		if(!is_null($noDiff) && $noDiff){
			$url = $url.'&noDifferential=true';
		}

		$result = $this->executeCURL($url);

		$SearchObj = new SearchResult();
		$SearchObj->fromJsonInfo($result);
		
		return $SearchObj;
	}
	
    function executeCURL($uri,$CorpNum = null,$userID = null,$isPost = false, $action = null, $postdata = null,$isMultiPart=false) {
		
		$http = curl_init(($this->ServiceURL).$uri);
		$header = array();

		$header[] = 'Authorization: Bearer '.$this->getsession_Token(null);
		$header[] = 'x-api-version: '.$this->VERS;
				
		curl_setopt($http, CURLOPT_HTTPHEADER,$header);
		curl_setopt($http, CURLOPT_RETURNTRANSFER, TRUE);
		
		$responseJson = curl_exec($http);
		$http_status = curl_getinfo($http, CURLINFO_HTTP_CODE);
		
		curl_close($http);
			
		if($http_status != 200) {
			return new JusolinkException($responseJson);
		}
		
		return $this->Linkhub->json_decode($responseJson);
	}
}


class SearchResult
{
	var $searches;
	var $deletedWord;
	var $suggest;
	var $sidoCount;
	var $numFound;
	var $listSize;
	var $totalPage;
	var $page;
	var $chargeYN;
	var $juso;
	
	function fromJsonInfo($jsonInfo){
		isset($jsonInfo->searches) ? ($this->searches = $jsonInfo->searches): null;
		isset($jsonInfo->deletedWord) ? ($this->deletedWord = $jsonInfo->deletedWord) : $this->deletedWord = array();
		isset($jsonInfo->suggest) ? ($this->suggest = $jsonInfo->suggest) : null;
		isset($jsonInfo->sidoCount) ? ($this->sidoCount = $jsonInfo->sidoCount) : null;
		isset($jsonInfo->numFound) ? ($this->numFound = $jsonInfo->numFound) : null;
		isset($jsonInfo->listSize) ? ($this->listSize = $jsonInfo->listSize) : null;
		isset($jsonInfo->totalPage) ? ($this->totalPage = $jsonInfo->totalPage) : null;
		isset($jsonInfo->page) ? ($this->page = $jsonInfo->page) : null;
		isset($jsonInfo->chargeYN) ? ($this->chargeYN = $jsonInfo->chargeYN) : null;
			
		if(isset($jsonInfo->juso)){
			$JusoList = array();
		
			for($i=0; $i < Count($jsonInfo->juso);$i++){
				$JusoListObj = new Juso();
				$JusoListObj->fromjsonInfo($jsonInfo->juso[$i]);
				$JusoList[$i] = $JusoListObj;
			}

			$this->juso = $JusoList;
		}
	}
}

class Juso {
	var $sectionNum;
	var $roadAddr1;
	var $roadAddr2;
	var $jibunAddr;
	var $detailBuildingName;
	var $zipcode;
	var $dongCode;
	var $streetCode;
	var $relatedJibun;

	function fromjsonInfo($jsonInfo){
		isset($jsonInfo->sectionNum) ? ($this->sectionNum = $jsonInfo->sectionNum) : null;
		isset($jsonInfo->roadAddr1) ? ($this->roadAddr1 = $jsonInfo->roadAddr1) : null;
		isset($jsonInfo->roadAddr2) ? ($this->roadAddr2 = $jsonInfo->roadAddr2) : null;
		isset($jsonInfo->jibunAddr) ? ($this->jibunAddr = $jsonInfo->jibunAddr) : null;
		isset($jsonInfo->detailBuildingName) ? $this->detailBuildingName = $jsonInfo->detailBuildingName : $this->detailBuildingName = array();
		isset($jsonInfo->zipcode) ? ($this->zipcode = $jsonInfo->zipcode) : null;
		isset($jsonInfo->dongCode) ? ($this->dongCode = $jsonInfo->dongCode) : null;
		isset($jsonInfo->streetCode) ? ($this->streetCode = $jsonInfo->streetCode) : null;
		isset($jsonInfo->relatedJibun) ? $this->relatedJibun = $jsonInfo->relatedJibun : $this->relatedJibun = array();
	}
}

// 예외클래스
class JusolinkException {
	var $code;
	var $message;

	function JusolinkException($responseJson) {
		if(is_a($responseJson,'LinkhubException')) {
			$this->code = $responseJson->code;
			$this->message = $responseJson->message;
			return $this;
		}
		$json = new Services_JSON();
		$result = $json->decode($responseJson);
		$this->code = $result->code;
		$this->message = $result->message;
		$this->isException = true;
		return $this;
	}
	function __toString() {
		return "[code : {$this->code}] : {$this->message}\n";
	}
}
?>
