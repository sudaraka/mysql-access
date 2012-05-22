<?php
/* mysql-acces.php: PHP Class with subroutines to manupiulate MySQL database
 *
 * Created: 09/30/2004
 * Version: 1.3
 *
 * Copyright (C) 2004-2012 Sudaraka Wijesinghe <sudaraka.wijesinghe@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

class MySQLAccess
{
	const OBJECT=1;
	const ASSOC=2;
	const FIELD=3;

	var $dbHost=null;
	var $dbDatabase;
	var $Records;
	var $LastIdentity;
	var $LowerCaseSQL;
	var $PageSize;
	var $CurrentPage;
	var $TotalPages;
	var $RecordCount;
	var $UnpagedRecordCount;
	var $PageStartingRecord;
	var $PageEndingRecord;
	var $SQLStatementBlocks;
	var $ConnectionID=null;

	function MySQLAccess($dbHost=null)
	{
		global $DB_HOST;

		$this->dbHost=(is_array($dbHost))?$dbHost:$DB_HOST;

		if(strlen($this->dbHost["Host"])<1 || strlen($this->dbHost["Username"])<1 || strlen($this->dbHost["Database"])<1)
			throw new Exception("Invalid DB parameters");

		$this->LowerCaseSQL=false;
		$this->PageSize=10;
		$this->CurrentPage=1;
		$this->RecordCount=0;
		$this->UnpagedRecordCount=0;
		$this->SQLStatementBlocks=null;
	}

	function __destruct()
	{
		if(is_resource($this->ConnectionID)) mysql_close($this->ConnectionID);
	}

	/**
	 * Connect to MySQL server based on the parameteres set on
	 * current instance of the class
	 *
	 * @access private
	 */
	function Connect()
	{
		if(!is_resource($this->ConnectionID))
		{
			$this->ConnectionID=@mysql_connect($this->dbHost["Host"], $this->dbHost["Username"], $this->dbHost["Password"]) or die("<b>Failed to connect to server: </b>".mysql_error());
			if($this->ConnectionID===false) die("<b>Failed to get resource id: </b>".mysql_error());
			@mysql_select_db($this->dbHost["Database"], $this->ConnectionID) or die(mysql_errno($this->ConnectionID).": ".mysql_error($this->ConnectionID)."<br/><b>Failed to open DB: </b>".mysql_error());;
		}
	}

	/**
	 * Execute SELECT SQL statements and return rows as an object array
	 *
	 * @param string $SQLStetement
	 * @return Array of objects
	 * @access public
	 */
	function ExecuteRecords($SQLStetement, $ReturnType=MySQLAccess::OBJECT)
	{
		$this->Records=array();

		$SQLStetement=str_replace("\r\n", " ", $SQLStetement);
		$SQLStetement=str_replace("\n", " ", $SQLStetement);
		$SQLStetement=str_replace("\t", " ", $SQLStetement);

		$SQLStetement=trim($SQLStetement);
		if(!preg_match("/^(SELECT\s).+(\sFROM\s).+$/i",$SQLStetement))
			throw new Exception("Invalid SQL statement for ExecuteRecords: $SQLStetement");

		$this->Connect();

		if($this->LowerCaseSQL) $SQLStetement=strtolower($SQLStetement);
		if(!($rowset=mysql_query($SQLStetement, $this->ConnectionID))) throw new Exception(mysql_error($this->ConnectionID)."\r\nSQL: $SQLStetement", mysql_errno($this->ConnectionID));

		$this->RecordCount=mysql_affected_rows($this->ConnectionID);
		$this->UnpagedRecordCount=$this->RecordCount;

		switch($ReturnType)
		{
			case MySQLAccess::OBJECT:
			{
				while($row=mysql_fetch_object($rowset))
				{
					$this->Records[]=$row;
				}
				break;
			}
			case MySQLAccess::ASSOC:
			{
				while($row=mysql_fetch_assoc($rowset))
				{
					$this->Records[]=$row;
				}
				break;
			}
			case MySQLAccess::FIELD:
			{
				while($row=mysql_fetch_field($rowset))
				{
					$this->Records[]=$row;
				}
				break;
			}
		}

		mysql_free_result($rowset);

		return $this->Records;
	}

	function ExecuteRecordsNoValidate($SQLStetement)
	{
		$this->Records=array();

		if($this->ConnectionID===false) return $this->Records;

		$SQLStetement=str_replace("\r\n", " ", $SQLStetement);
		$SQLStetement=str_replace("\n", " ", $SQLStetement);
		$SQLStetement=str_replace("\t", " ", $SQLStetement);

		$SQLStetement=trim($SQLStetement);

		$this->Connect();

		if($this->LowerCaseSQL) $SQLStetement=strtolower($SQLStetement);
		if(!($rowset=mysql_query($SQLStetement, $this->ConnectionID))) throw new Exception(mysql_error($this->ConnectionID)."\r\nSQL: $SQLStetement", mysql_errno($this->ConnectionID));

		$this->RecordCount=mysql_affected_rows($this->ConnectionID);

		while($row=mysql_fetch_object($rowset))
		{
			$this->Records[]=$row;
		}

		mysql_free_result($rowset);

		return $this->Records;
	}

	function ExecuteNonQuery($SQLStetement, $AutoCommit=false)
	{
		$SQLStetement=str_replace("\r\n", " ", $SQLStetement);
		$SQLStetement=str_replace("\n", " ", $SQLStetement);
		$SQLStetement=str_replace("\t", " ", $SQLStetement);

		$SQLStetement=trim($SQLStetement);
		if(!preg_match("/^(INSERT\sINTO\s)|(UPDATE\s)|(DELETE\sFROM\s).+$/i",$SQLStetement))
			throw new Exception("Invalid SQL statement for ExecuteNonQuery: $SQLStetement");

		$this->Connect();
		if($this->ConnectionID===false) return 0;

		if($this->LowerCaseSQL) $SQLStetement=strtolower($SQLStetement);
		if(!($rowset=mysql_query($SQLStetement, $this->ConnectionID))) throw new Exception(mysql_error($this->ConnectionID)."\r\nSQL: $SQLStetement", mysql_errno($this->ConnectionID));
		$this->RecordCount=mysql_affected_rows($this->ConnectionID);
		if(strstr(strtoupper($SQLStetement),"INSERT INTO"))
			$this->LastIdentity=mysql_insert_id($this->ConnectionID);

		if($AutoCommit) $this->Commit();

		return $this->RecordCount;
	}

	function ParseSQL($SQLStetement)
	{
		$strUCaseSQL=strtoupper($SQLStetement);
		$SQL_STRUCTURE=array(
			new SQLStatementClaus(" WHERE "),
			new SQLStatementClaus("SELECT "),
			new SQLStatementClaus(" FROM "),
			new SQLStatementClaus(" ORDER BY "),
			new SQLStatementClaus(" GROUP BY "),
			new SQLStatementClaus(" HAVING "),
			new SQLStatementClaus(" LIMIT ")
		);

		//Find start points of all clauses
		for($idx=0;$idx<sizeof($SQL_STRUCTURE);$idx++)
		{
			$SQL_STRUCTURE[$idx]->StartPosition=strpos($strUCaseSQL, $SQL_STRUCTURE[$idx]->ClausName, 0);
			if(strlen($SQL_STRUCTURE[$idx]->StartPosition)<1) $SQL_STRUCTURE[$idx]->StartPosition=-1;
		}
		//Sort by StartPosition
		usort($SQL_STRUCTURE, 'SQLStatementClausCompare');

		//Remove empty clauses
		while(sizeof($SQL_STRUCTURE)>0)
		{
			if($SQL_STRUCTURE[0]->StartPosition>-1) break;
			array_shift($SQL_STRUCTURE);
		}

		//Find claus data
		for($idx=0;$idx<sizeof($SQL_STRUCTURE);$idx++)
		{
			$intClausLength=strlen($SQLStetement);
			if($idx<sizeof($SQL_STRUCTURE)-1)
			{
				$intClausLength=$SQL_STRUCTURE[$idx+1]->StartPosition-($SQL_STRUCTURE[$idx]->StartPosition+strlen($SQL_STRUCTURE[$idx]->ClausName));
			}
			$SQL_STRUCTURE[$idx]->ClausData=substr($SQLStetement, $SQL_STRUCTURE[$idx]->StartPosition+strlen($SQL_STRUCTURE[$idx]->ClausName), $intClausLength);
		}

		$this->SQLStatementBlocks=$SQL_STRUCTURE;
		return $SQL_STRUCTURE;
	}

	function GetSQLBlockValue($ClausName)
	{
		$ClausName=strtoupper(trim($ClausName));
		foreach($this->SQLStatementBlocks as $Claus)
		{
			if(trim($Claus->ClausName)==$ClausName) return $Claus->ClausData;
		}
		return "";
	}

	function DirectlyPageable($SQLStetement)
	{
		$SQLStetement=strtoupper($SQLStetement);
		if(strpos($SQLStetement, "UNION ")) return false;
		return true;
	}

	function ExecutePager($SQLStetement, $PageNumber, $PageSize=10)
	{
		$this->Records=array();

		if($PageNumber<1 || strlen($PageNumber)<1) $PageNumber=1;
		$this->Records=array();
		$this->CurrentPage=$PageNumber;
		$this->PageSize=$PageSize;

		$SQLStetement=str_replace("\r\n", " ", $SQLStetement);
		$SQLStetement=str_replace("\n", " ", $SQLStetement);
		$SQLStetement=str_replace("\t", " ", $SQLStetement);

		if(!$this->DirectlyPageable($SQLStetement))
		{
			$arrPage=null;
			$arrPage=$this->ExecuteRecords($SQLStetement);
			$arrTmp=array_chunk($arrPage, $this->PageSize);
			$this->TotalPages=sizeof($arrTmp);
			$arrPage=$arrTmp[$this->CurrentPage-1];
			$this->Records=$arrPage;
			return $arrPage;
		}

		$SQLStetement=trim($SQLStetement);
		if(!preg_match("/^(SELECT\s).+(\sFROM\s).+$/i",$SQLStetement))
			throw new Exception("Invalid SQL statement for ExecutePager: $SQLStetement");

		$this->ParseSQL($SQLStetement);
		$strRecordSource=$this->GetSQLBlockValue("from");
		$strCriteria=$this->GetSQLBlockValue("where");
		$strGroupBy=$this->GetSQLBlockValue("group by");
		$strHaving=$this->GetSQLBlockValue("having");

		$this->Connect();
		if($this->ConnectionID===false) return $this->Records;

		if(!($rowset=mysql_query("SELECT 0 FROM ".$strRecordSource.((strlen($strCriteria)>0)?" WHERE $strCriteria":"").((strlen($strGroupBy)>0)?" GROUP BY $strGroupBy":"").((strlen($strHaving)>0)?" HAVING $strHaving":""), $this->ConnectionID))) throw new Exception(mysql_error($this->ConnectionID)."\r\nSQL (count): $SQLStetement", mysql_errno($this->ConnectionID));
		$this->RecordCount=0;
		if($row=mysql_fetch_object($rowset))
			$this->RecordCount=mysql_affected_rows($this->ConnectionID);
		$this->UnpagedRecordCount=$this->RecordCount;

		mysql_free_result($rowset);

		$intPageCount=ceil($this->RecordCount/$this->PageSize);
		if($this->CurrentPage>$intPageCount) $this->CurrentPage=1;

		$this->TotalPages=$intPageCount;

		$SQL="";
		foreach($this->SQLStatementBlocks as $Claus)
		{
			if(trim($Claus->ClausName)!="LIMIT")
				$SQL.=$Claus->ClausName.$Claus->ClausData;
		}
		$SQL.=" LIMIT ".($this->CurrentPage-1)*$this->PageSize.", ".$this->PageSize;
		$this->PageStartingRecord=(($this->CurrentPage-1)*$this->PageSize)+1;
		$this->PageEndingRecord=(($this->CurrentPage-1)*$this->PageSize)+$this->PageSize;


		if(!($rowset=mysql_query($SQL, $this->ConnectionID))) throw new Exception(mysql_error($this->ConnectionID)."\r\nSQL: $SQLStetement", mysql_errno($this->ConnectionID));

		while($row=mysql_fetch_object($rowset))
		{
			$this->Records[]=$row;
		}

		mysql_free_result($rowset);

		return $this->Records;
	}

	function BeginTrans()
	{
		$this->Connect();
		if($this->ConnectionID===false) return false;
		if(!(mysql_query("BEGIN", $this->ConnectionID))) throw new Exception(mysql_error($this->ConnectionID)."\r\nFailed to begin transaction", mysql_errno($this->ConnectionID));
		return true;
	}

	function Commit()
	{
		$this->Connect();
		if($this->ConnectionID===false) return false;
		if(!(mysql_query("COMMIT", $this->ConnectionID))) throw new Exception(mysql_error($this->ConnectionID)."\r\nFailed to commit transaction", mysql_errno($this->ConnectionID));
		return true;
	}

	function Rollback()
	{
		$this->Connect();
		if($this->ConnectionID===false) return false;
		if(!(mysql_query("ROLLBACK", $this->ConnectionID))) throw new Exception(mysql_error($this->ConnectionID)."\r\nFailed to rollback transaction", mysql_errno($this->ConnectionID));
		return true;
	}

	function Ping()
	{
		$this->Connect();
		return mysql_ping($this->ConnectionID);
	}

	function LastError()
	{
		return array(mysql_errno($this->ConnectionID), mysql_error($this->ConnectionID));
	}
}

class SQLStatementClaus
{
	var $ClausName="";
	var $ClausData="";
	var $StartPosition=-1;

	function SQLStatementClaus($ClausName, $ClausData="", $StartPosition=-1)
	{
		$this->ClausName=$ClausName;
		if(strlen($ClausData)>0) $this->ClausData=$ClausData;
		if($StartPosition>-1) $this->StartPosition=$StartPosition;
	}
}

function SQLStatementClausCompare($Claus1, $Claus2)
{
	return $Claus1->StartPosition>$Claus2->StartPosition;
}

