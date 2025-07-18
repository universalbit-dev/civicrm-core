<?php
/*
 *  File for the TestMailing class
 *
 *  (PHP 5)
 *
 *   @package   CiviCRM
 *
 *   This file is part of CiviCRM
 *
 *   CiviCRM is free software; you can redistribute it and/or
 *   modify it under the terms of the GNU Affero General Public License
 *   as published by the Free Software Foundation; either version 3 of
 *   the License, or (at your option) any later version.
 *
 *   CiviCRM is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU Affero General Public License for more details.
 *
 *   You should have received a copy of the GNU Affero General Public
 *   License along with this program.  If not, see
 *   <http://www.gnu.org/licenses/>.
 */

/**
 *  Test APIv3 civicrm_mailingab_* functions
 *
 * @package   CiviCRM
 * @group headless
 */
class api_v3_MailingABTest extends CiviUnitTestCase {
  protected $_mailingID_A;
  protected $_mailingID_B;
  protected $_mailingID_C;
  protected $_params;
  protected $_apiversion = 3;
  protected $_entity = 'MailingAB';
  protected $_groupID;

  public function setUp(): void {
    parent::setUp();
    $this->useTransaction(TRUE);
    $this->createLoggedInUser();
    $this->_mailingID_A = $this->createMailing([
      'subject' => 'subject a ' . time(),
      'body_text' => 'body_text a ' . time(),
    ]);
    $this->_mailingID_B = $this->createMailing([
      'subject' => 'subject b ' . time(),
      'body_text' => 'body_text b ' . time(),
    ]);
    $this->_mailingID_C = $this->createMailing([
      'subject' => 'not yet ' . time(),
      'body_text' => 'not yet ' . time(),
    ]);
    $this->_groupID = $this->groupCreate();

    $this->_params = [
      'mailing_id_a' => $this->_mailingID_A,
      'mailing_id_b' => $this->_mailingID_B,
      'mailing_id_c' => $this->_mailingID_C,
      'testing_criteria' => 'subject',
      'winner_criteria' => 'open',
      'declare_winning_time' => '+2 days',
      'group_percentage' => 10,
    ];
  }

  /**
   * Test civicrm_mailing_create.
   */
  public function testMailingABCreateSuccess(): void {
    $result = $this->callAPISuccess($this->_entity, 'create', $this->_params);
    $this->assertTrue(is_numeric($result['id']), "In line " . __LINE__);
    $this->assertEquals($this->_params['group_percentage'], $result['values'][$result['id']]['group_percentage']);
  }

  /**
   * Test civicrm_mailing_delete.
   */
  public function testMailerDeleteSuccess(): void {
    $result = $this->callAPISuccess($this->_entity, 'create', $this->_params);

    $this->assertDBQuery(1, "SELECT count(*) FROM civicrm_mailing_abtest WHERE id = %1", [
      1 => [$result['id'], 'Integer'],
    ]);
    $this->assertDBQuery(3, "SELECT count(*) FROM civicrm_mailing WHERE id IN (%1,%2,%3)", [
      1 => [$this->_mailingID_A, 'Integer'],
      2 => [$this->_mailingID_B, 'Integer'],
      3 => [$this->_mailingID_C, 'Integer'],
    ]);

    $this->callAPISuccess($this->_entity, 'delete', ['id' => $result['id']]);

    $this->assertDBQuery(0, "SELECT count(*) FROM civicrm_mailing_abtest WHERE id = %1", [
      1 => [$result['id'], 'Integer'],
    ]);
    $this->assertDBQuery(0, "SELECT count(*) FROM civicrm_mailing WHERE id IN (%1,%2,%3)", [
      1 => [$this->_mailingID_A, 'Integer'],
      2 => [$this->_mailingID_B, 'Integer'],
      3 => [$this->_mailingID_C, 'Integer'],
    ]);
  }

  /**
   * @return array
   */
  public static function groupPctProvider(): array {
    // array(int $totalSize, int $groupPct, int $expectedCountA, $expectedCountB, $expectedCountC)
    $cases = [];
    $cases[] = [400, 7, 28, 28, 344];
    $cases[] = [100, 10, 10, 10, 80];
    $cases[] = [50, 20, 10, 10, 30];
    $cases[] = [50, 10, 5, 5, 40];
    $cases[] = [3, 10, 1, 1, 1];
    $cases[] = [2, 10, 1, 1, 0];
    $cases[] = [1, 10, 1, 0, 0];
    return $cases;
  }

  /**
   * Create a test and ensure that all three mailings (A/B/C) wind up with the correct
   * number of recipients.
   *
   * @param $totalGroupContacts
   * @param $groupPct
   * @param $expectedCountA
   * @param $expectedCountB
   * @param $expectedCountC
   * @dataProvider groupPctProvider
   */
  public function testDistribution($totalGroupContacts, $groupPct, $expectedCountA, $expectedCountB, $expectedCountC) {
    $result = $this->groupContactCreate($this->_groupID, $totalGroupContacts, TRUE);
    $this->assertEquals($totalGroupContacts, $result['added'], "in line " . __LINE__);

    $params = $this->_params;
    $params['group_percentage'] = $groupPct;
    $result = $this->callAPISuccess($this->_entity, 'create', $params);

    $this->callAPISuccess('Mailing', 'create', [
      'id' => $this->_mailingID_A,
      'groups' => ['include' => [$this->_groupID]],
    ]);
    $this->assertJobCounts(0, 0, 0);

    $this->callAPISuccess('MailingAB', 'submit', [
      'id' => $result['id'],
      'status' => 'Testing',
      'scheduled_date' => date('YmdHis'),
      'approval_date' => date('YmdHis'),
    ]);
    $this->assertRecipientCounts($expectedCountA, $expectedCountB, $expectedCountC);
    $this->assertJobCounts(1, 1, 0);

    $this->callAPISuccess('MailingAB', 'submit', [
      'id' => $result['id'],
      'status' => 'Final',
      'scheduled_date' => date('YmdHis'),
      'approval_date' => date('YmdHis'),
    ]);
    $this->assertRecipientCounts($expectedCountA, $expectedCountB, $expectedCountC);
    $this->assertJobCounts(1, 1, 1);
  }

  /**
   * Create a test. Declare the second mailing a winner. Ensure that key
   * fields propagate to the final mailing.
   */
  public function testSubmitWinnerId(): void {
    $checkSyncFields = ['subject', 'body_text'];

    $result = $this->groupContactCreate($this->_groupID, 20, TRUE);
    $this->assertEquals(20, $result['added'], "in line " . __LINE__);

    $params = $this->_params;
    $params['group_percentage'] = 10;
    $result = $this->callAPISuccess($this->_entity, 'create', $params);

    $this->callAPISuccess('Mailing', 'create', [
      'id' => $this->_mailingID_A,
      'groups' => ['include' => [$this->_groupID]],
    ]);
    $this->assertJobCounts(0, 0, 0);

    $this->callAPISuccess('MailingAB', 'submit', [
      'id' => $result['id'],
      'status' => 'Testing',
      'scheduled_date' => 'now',
      'approval_date' => 'now',
    ]);
    $this->assertJobCounts(1, 1, 0);

    $b = $this->getApiValues('Mailing', ['id' => $this->_mailingID_B], $checkSyncFields);
    $c = $this->getApiValues('Mailing', ['id' => $this->_mailingID_C], $checkSyncFields);
    $this->assertNotEquals($b, $c);

    $this->callAPISuccess('MailingAB', 'submit', [
      'id' => $result['id'],
      'status' => 'Final',
      'winner_id' => $this->_mailingID_B,
      'scheduled_date' => 'now',
      'approval_date' => 'now',
    ]);
    $this->assertJobCounts(1, 1, 1);

    $b = $this->getApiValues('Mailing', ['id' => $this->_mailingID_B], $checkSyncFields);
    $c = $this->getApiValues('Mailing', ['id' => $this->_mailingID_C], $checkSyncFields);
    $this->assertEquals($b, $c);
  }

  /**
   * Lookup a record via API. Return *only* the expected values.
   *
   * @param $entity
   * @param $filter
   * @param $return
   * @return array
   */
  protected function getApiValues($entity, $filter, $return) {
    $rec = $this->callAPISuccess($entity, 'getsingle', $filter + ['return' => $return]);
    return CRM_Utils_Array::subset($rec, $return);
  }

  /**
   * @param $expectedCountA
   * @param $expectedCountB
   * @param $expectedCountC
   */
  protected function assertRecipientCounts($expectedCountA, $expectedCountB, $expectedCountC) {
    $countA = $this->callAPISuccess('MailingRecipients', 'getcount', ['mailing_id' => $this->_mailingID_A]);
    $countB = $this->callAPISuccess('MailingRecipients', 'getcount', ['mailing_id' => $this->_mailingID_B]);
    $countC = $this->callAPISuccess('MailingRecipients', 'getcount', ['mailing_id' => $this->_mailingID_C]);
    $this->assertEquals($expectedCountA, $countA, "check mailing recipients A in line " . __LINE__);
    $this->assertEquals($expectedCountB, $countB, "check mailing recipients B in line " . __LINE__);
    $this->assertEquals($expectedCountC, $countC, "check mailing recipients C in line " . __LINE__);
  }

  /**
   * @param $expectedA
   * @param $expectedB
   * @param $expectedC
   */
  protected function assertJobCounts($expectedA, $expectedB, $expectedC) {
    $this->assertDBQuery($expectedA, 'SELECT count(*) FROM civicrm_mailing_job WHERE mailing_id = %1', [
      1 => [
        $this->_mailingID_A,
        'Integer',
      ],
    ]);
    $this->assertDBQuery($expectedB, 'SELECT count(*) FROM civicrm_mailing_job WHERE mailing_id = %1', [
      1 => [
        $this->_mailingID_B,
        'Integer',
      ],
    ]);
    $this->assertDBQuery($expectedC, 'SELECT count(*) FROM civicrm_mailing_job WHERE mailing_id = %1', [
      1 => [
        $this->_mailingID_C,
        'Integer',
      ],
    ]);
  }

}
