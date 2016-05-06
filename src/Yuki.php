<?php

namespace FwsDevelopment;

use Carbon\Carbon;
use Soapclient;

Class Yuki
{
	private $session_id;

	private $administrationId;

	public static function connect ($service, $administration = null)
	{	
		is_null($administration) ? $administration = config('yuki.administration_id') : $administration = $administration;
		$url = NULL;

		switch($service)
		{
			case 'accounting':
				$url = "https://api.yukiworks.nl/ws/Accounting.asmx?WSDL";
				break;
			case 'sales':
				$url = "https://api.yukiworks.nl/ws/Sales.asmx?WSDL";
				break;
			default:
				$url = "https://api.yukiworks.nl/ws/Accounting.asmx?WSDL";
				break;
		}

		try
		{
			$soap = new Soapclient($url);
			$res = $soap->Authenticate(['accessKey' => config('yuki.access_key')]);

			$adminResult = $soap->AdministrationID($this->session_id, $administration);
			$this->session_id = $res->AuthenticateResult;
			$this->administrationId = $adminResult->AdministrationIDResult;

			return $soap;
		}
		catch (SoapFault $result)
		{
			return $result;
		}
	}

	public static function makeJournalEntrys($entrys)
	{
		$xml = "";

		foreach ($entrys as $key => $entry)
		{
			$xml .= "
				<JournalEntry>
					<ContactName>Order</Contactname>
					<EntryDate>".Carbon::now()->format('Y-m-d')."</EntryDate>
					<GLAccount>".$key."</GLAccount>
					<Amount>".$entry."</Amount>
					<Description>test order</Description>
				</JournalEntry>
			";
		}

		return $xml;
	}

	public static function insertOrder($journals, $subject)
	{
		try {
			$soap = $this->connect('accounting');

			$xml = "
				<Journal xmlns='urn:xmlns:http://www.theyukicompany.com:journal' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'>
					<AdministrationID>".$administration_id."<AdministrationID>
					<DocumentSubject>".$subject."</DocumentSubject>
					<JournalType></JournalType>
					".$this->makeJournalEntrys($journals)."
				</Journal>
			";

			$xmlVar = new SoapVar('<ns1:xmlDoc>'.$xml.'</ns1:xmlDoc>', XSD_ANYXML);

			$result = $soap->ProcessJournal($this->session_id, $this->administrationId, $xmlVar);

			return $result->ProcessJournalResult;
		}
		catch(SoapFault $fault)
		{
			return $fault;
		}
	}
}