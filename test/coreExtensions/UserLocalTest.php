<?php
/**
 * Created by Bruno DA SILVA, working for Combodo
 * Date: 21/11/2019
 * Time: 09:14
 */

namespace coreExtensions;


use Combodo\iTop\Test\UnitTest\ItopTestCase;
use UserLocal;
use UserLocalPasswordPolicyMockNotValid;
use UserLocalPasswordPolicyMockNotValidBis;
use UserLocalPasswordPolicyMockValid;
use UserLocalPasswordPolicyMockValidBis;
use UserLocalPasswordValidity;
use UserPasswordPolicyRegex;

/**
 * test class for UserLocal class
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class UserLocalTest extends ItopTestCase
{

	public function setUp()
	{
		parent::setUp();

		require_once(APPROOT.'application/startup.inc.php');
		require_once (APPROOT.'test/coreExtensions/UserLocalTest/UserLocalPasswordPolicyMock.php');
		require_once (APPROOT.'env-production/authent-local/model.authent-local.php');
	}

	/**
	 * @dataProvider ProviderValidatePassword
	 *
	 */
	public function testValidatePassword($sPassword, $aValidatorCollection, $aConfigValueMap, $bExpectedCheckStatus, $expectedCheckIssues = null)
	{
		$configMock = $this->createMock(\Config::class);

		$configMock
			->method('GetModuleSetting')
			->willReturnMap($aConfigValueMap);

		/** @var UserLocal $oUserLocal */
		$oUserLocal = \MetaModel::NewObject('UserLocal', array('login' => 'john'));
		/** @var \ormLinkSet $oProfileSet */
		$oProfileSet = $oUserLocal->Get('profile_list');

		$oProfileSet->AddItem(
			\MetaModel::NewObject('URP_UserProfile', array('profileid' => 1))
		);

		$oUserLocal->ValidatePassword($sPassword, $configMock, $aValidatorCollection);

		list($bCheckStatus, $aCheckIssues, $aSecurityIssues) =  $oUserLocal->CheckToWrite();

		$this->assertSame($bExpectedCheckStatus, $bCheckStatus);

		if (isset($expectedCheckIssues))
		{
			$this->assertContains($expectedCheckIssues, $aCheckIssues);
		}
	}

	public function ProviderValidatePassword()
	{
		parent::setUp();
		require_once (APPROOT.'env-production/authent-local/model.authent-local.php');
		require_once (APPROOT.'test/coreExtensions/UserLocalTest/UserLocalPasswordPolicyMock.php');

		$oUserPasswordPolicyRegex = new UserPasswordPolicyRegex();

		$oUserLocalPasswordPolicyMockValid = new UserLocalPasswordPolicyMockValid();
		$oUserLocalPasswordPolicyMockNotValid = new UserLocalPasswordPolicyMockNotValid();
		$oUserLocalPasswordPolicyMockValidBis = new UserLocalPasswordPolicyMockValidBis();
		$oUserLocalPasswordPolicyMockNotValidBis = new UserLocalPasswordPolicyMockNotValidBis();


		return array(
			'validPattern' => array(
				'password' => 'foo',
				'aValidatorCollection' => array(
					$oUserPasswordPolicyRegex,
				),
				'valueMap' => array(
					array('authent-local', 'password_validation.pattern', null, '.{1,10}')
				),
				'expectedCheckStatus' => true,
			),
			'notValidPattern' => array(
				'password' => 'foo',
				'aValidatorCollection' => array(
					$oUserPasswordPolicyRegex,
				),
				'valueMap' => array(
					array('authent-local', 'password_validation.pattern', null, '.{6,10}')
				),
				'expectedCheckStatus' => false,
			),
			'noPattern' => array(
				'password' => 'foo',
				'aValidatorCollection' => array(
					$oUserPasswordPolicyRegex,
				),
				'valueMap' => array(
					array('authent-local', 'password_validation.pattern', null, '')
				),
				'expectedCheckStatus' => true,
			),
			'validClass' => array(
				'password' => 'foo',
				'aValidatorCollection' => array(
					$oUserLocalPasswordPolicyMockValid,
				),
				'valueMap' => array(),
				'expectedCheckStatus' => true,
			),
			'notValidClass' => array(
				'password' => 'foo',
				'aValidatorCollection' => array(
					$oUserLocalPasswordPolicyMockNotValid,
				),
				'valueMap' => array(),
				'expectedCheckStatus' => false,
			),

			'validation_composition_10' => array(
				'password' => 'foo',
				'aValidatorCollection' => array(
					$oUserLocalPasswordPolicyMockValid,
					$oUserLocalPasswordPolicyMockNotValid,
				),
				'valueMap' => array(),
				'expectedCheckStatus' => false,
				'expectedCheckIssues' => 'UserLocalPasswordPolicyMockNotValid',
			),


			'validation_composition_01' => array(
				'password' => 'foo',
				'aValidatorCollection' => array(
					$oUserLocalPasswordPolicyMockNotValid,
					$oUserLocalPasswordPolicyMockValid,
				),
				'valueMap' => array(),
				'expectedCheckStatus' => false,
				'expectedCheckIssues' => 'UserLocalPasswordPolicyMockNotValid',
			),

			'validation_composition_11' => array(
				'password' => 'foo',
				'aValidatorCollection' => array(
					$oUserLocalPasswordPolicyMockValid,
					$oUserLocalPasswordPolicyMockValidBis,
				),
				'valueMap' => array(),
				'expectedCheckStatus' => true,
			),
			'validation_composition_00' => array(
				'password' => 'foo',
				'aValidatorCollection' => array(
					$oUserLocalPasswordPolicyMockNotValid,
					$oUserLocalPasswordPolicyMockNotValidBis,
				),
				'valueMap' => array(),
				'expectedCheckStatus' => false,
				'expectedCheckIssues' => 'UserLocalPasswordPolicyMockNotValid',
			),

		);
	}


	/**
	 * @dataProvider ProviderPasswordRenewal
	 *
	 */
	public function testPasswordRenewal($aUserLocalValues, $oExpectedBefore, $oExpectedAfter)
	{
		/** @var UserLocal $oUserLocal */
		$oUserLocal = \MetaModel::NewObject('UserLocal', $aUserLocalValues);
		/** @var \ormLinkSet $oProfileSet */
		$oProfileSet = $oUserLocal->Get('profile_list');

		$oProfileSet->AddItem(
			\MetaModel::NewObject('URP_UserProfile', array('profileid' => 1))
		);

		$this->assertEquals($oExpectedBefore, $oUserLocal->Get('password_renewed_date'));

		$oUserLocal->Set('password', 'foo');

		$this->assertEquals($oExpectedAfter, $oUserLocal->Get('password_renewed_date'));
	}

	public function ProviderPasswordRenewal()
	{
		$sNow = date(\AttributeDate::GetInternalFormat());
		$sYesterday = date(\AttributeDate::GetInternalFormat(), strtotime('-1 day'));
		$sTomorrow = date(\AttributeDate::GetInternalFormat(), strtotime('+1 day'));

		return array(
			'nominal case' => array(
				'aUserLocalValues' =>  array('login' => 'john'),
				'oExpectedBefore' => null,
				'oExpectedAfter' => $sNow,
			),
			'date initiated' => array(
				'aUserLocalValues' =>  array('login' => 'john', 'password_renewed_date' => $sYesterday),
				'oExpectedBefore' => $sYesterday,
				'oExpectedAfter' => $sNow,
			),
			'date initiated in the future' => array(
				'aUserLocalValues' =>  array('login' => 'john', 'password_renewed_date' => $sTomorrow),
				'oExpectedBefore' => $sTomorrow,
				'oExpectedAfter' => $sNow,
			),
		);
	}
}
