<?php

/**
 *  File for the EntryTest class
 *
 *  (PHP 5)
 *
 * @package   CiviCRM
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

use Civi\Api4\Batch;
use Civi\Api4\Campaign;
use Civi\Api4\Contribution;
use Civi\Api4\LineItem;

/**
 * @package   CiviCRM
 * @group headless
 */
class CRM_Batch_Form_EntryTest extends CiviUnitTestCase {

  /**
   * @var int
   */
  protected $relationshipTypeID;

  /**
   * @var int
   */
  protected $organizationContactID;

  /**
   * @var int
   */
  protected $membershipTypeID;

  /**
   * @var int
   */
  protected $membershipTypeID2;

  /**
   * @var int
   */
  protected $contactID;

  /**
   * @var int
   */
  protected $contactID2 = NULL;

  /**
   * @var int
   */
  protected $contactID3 = NULL;

  /**
   * @var int
   */
  protected $contactID4 = NULL;

  /**
   * @throws \CRM_Core_Exception
   */
  public function setUp(): void {
    parent::setUp();
    \CRM_Core_BAO_ConfigSetting::enableComponent('CiviMember');
    \CRM_Core_BAO_ConfigSetting::enableComponent('CiviCampaign');

    $params = [
      'contact_type_a' => 'Individual',
      'contact_type_b' => 'Organization',
      'name_a_b' => 'Test Employee of',
      'name_b_a' => 'Test Employer of',
    ];
    $this->relationshipTypeID = $this->relationshipTypeCreate($params);
    $this->organizationContactID = $this->organizationCreate();
    $params = [
      'name' => 'Mickey Mouse Club Member',
      'description' => NULL,
      'minimum_fee' => 1500,
      'duration_unit' => 'year',
      'member_of_contact_id' => $this->organizationContactID,
      'period_type' => 'fixed',
      'duration_interval' => 1,
      'financial_type_id' => 1,
      'relationship_type_id' => $this->relationshipTypeID,
      'visibility' => 'Public',
      'is_active' => 1,
      'fixed_period_start_day' => 101,
      'fixed_period_rollover_day' => 1231,
      'domain_id' => CRM_Core_Config::domainID(),
    ];
    $membershipType = $this->callAPISuccess('membership_type', 'create', $params);
    $this->membershipTypeID = $membershipType['id'];

    $this->organizationCreate([], 'organization_2');
    $params = [
      'name' => 'General',
      'duration_unit' => 'year',
      'duration_interval' => 1,
      'period_type' => 'rolling',
      'member_of_contact_id' => $this->ids['Contact']['organization_2'],
      'domain_id' => 1,
      'financial_type_id' => 1,
      'is_active' => 1,
      'sequential' => 1,
      'visibility' => 'Public',
    ];
    $membershipType2 = $this->callAPISuccess('MembershipType', 'create', $params);
    $this->membershipTypeID2 = $membershipType2['id'];

    $this->membershipStatusCreate('test status');
    $this->contactID = $this->individualCreate();
    $contact2Params = [
      'first_name' => 'Anthonita',
      'middle_name' => 'J.',
      'last_name' => 'Anderson',
      'prefix_id' => 3,
      'suffix_id' => 3,
      'email' => 'b@c.com',
      'contact_type' => 'Individual',
    ];
    $this->contactID2 = $this->individualCreate($contact2Params);
    $this->contactID3 = $this->individualCreate(['first_name' => 'bobby', 'email' => 'c@d.com']);
    $this->contactID4 = $this->individualCreate(['first_name' => 'bobbynita', 'email' => 'c@de.com']);
  }

  /**
   * Tears down the fixture, for example, closes a network connection.
   * This method is called after a test is executed.
   */
  public function tearDown(): void {
    $this->quickCleanUpFinancialEntities();
    $this->quickCleanup(['civicrm_campaign', 'civicrm_batch', 'civicrm_entity_batch']);
    $this->relationshipTypeDelete($this->relationshipTypeID);
    if ($this->callAPISuccessGetCount('membership', ['id' => $this->membershipTypeID])) {
      $this->membershipTypeDelete(['id' => $this->membershipTypeID]);
    }
    parent::tearDown();
  }

  /**
   *  Test Import.
   *
   * @param string $thousandSeparator
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   *
   * @dataProvider getThousandSeparators
   */
  public function testProcessMembership(string $thousandSeparator): void {
    $this->setCurrencySeparators($thousandSeparator);

    $params = $this->getMembershipData();
    $this->createTestEntity('Batch', ['name' => 'membership', 'status_id:name' => 'Open', 'type_id:name' => 'Membership', 'item_count' => 3, 'total' => 3]);
    $this->getTestForm('CRM_Batch_Form_Entry', $params, ['id' => $this->ids['Batch']['default']])
      ->processForm();

    $memberships = $this->callAPISuccess('Membership', 'get')['values'];
    $this->assertCount(3, $memberships);

    $this->assertNotEmpty($memberships[1]['campaign_id']);

    //check start dates #1 should default to 1 Jan this year, #2 should be as entered
    $this->assertEquals(date('Y-m-d', strtotime('first day of January 2013')), $memberships[1]['start_date']);
    $this->assertEquals('2013-02-03', $memberships[2]['start_date']);
    $this->assertEquals('2013-12-31', $memberships[2]['end_date']);

    //check start dates #1 should default to 1 Jan this year, #2 should be as entered
    $this->assertEquals(date('Y-m-d', strtotime('last day of December 2013')), $memberships[1]['end_date']);
    $this->assertEquals(date('Y-m-d', strtotime('last day of December 2013')), $memberships[2]['end_date']);
    $this->assertEquals('2013-12-01', $memberships[3]['end_date']);

    //check start dates #1 should default to 1 Jan this year, #2 should be as entered
    $this->assertEquals(date('Y-m-d', strtotime('07/22/2013')), $memberships[1]['join_date']);
    $this->assertEquals(date('Y-m-d', strtotime('07/03/2013')), $memberships[2]['join_date']);
    $this->assertEquals(date('Y-m-d'), $memberships[3]['join_date']);
    $memberships = $this->callAPISuccess('Contribution', 'get', ['return' => ['total_amount', 'trxn_id']]);
    $this->assertEquals(3, $memberships['count']);
    foreach ($memberships['values'] as $key => $contribution) {
      $this->assertEquals($this->callAPISuccess('LineItem', 'getvalue', [
        'contribution_id' => $contribution['id'],
        'return' => 'line_total',

      ]), $contribution['total_amount']);
      $this->assertEquals(1500, $contribution['total_amount']);
      $this->assertEquals($params['field'][$key]['trxn_id'], $contribution['trxn_id']);
    }
  }

  /**
   *  Test Contribution Import.
   *
   * @param $thousandSeparator
   *
   * @dataProvider getThousandSeparators
   * @throws \CRM_Core_Exception
   */
  public function testProcessContribution($thousandSeparator): void {
    $this->setCurrencySeparators($thousandSeparator);
    $this->offsetDefaultPriceSet();
    $this->createTestEntity('Batch', ['name' => 'contributions', 'type_id:name' => 'Contribution', 'status_id:name' => 'Open', 'item_count' => 3, 'total' => 4500.45, 'data' => '{"values":[]}']);
    $this->getTestForm('CRM_Batch_Form_Entry', $this->getContributionData(), ['id' => $this->ids['Batch']['default']])->processForm();

    $contributions = Contribution::get(FALSE)
      ->addSelect('total_amount', 'financial_type_id')
      ->execute();
    $this->assertCount(3, $contributions);
    foreach ($contributions as $contribution) {
      $lineItem = LineItem::get(FALSE)
        ->addWhere('contribution_id', '=', $contribution['id'])
        ->execute()->single();
      $this->assertEquals($lineItem['line_total'], $contribution['total_amount']);
      $this->assertEquals($lineItem['financial_type_id'], $contribution['financial_type_id']);
    }
    $checkResult = $this->callAPISuccess('Contribution', 'get', ['check_number' => ['IS NOT NULL' => 1]]);
    $this->assertEquals(1, $checkResult['count']);
    $entityFinancialTrxn = $this->callAPISuccess('EntityFinancialTrxn', 'get', ['entity_table' => 'civicrm_contribution', 'entity_id' => $checkResult['id']]);
    $financialTrxn = $this->callAPISuccess('FinancialTrxn', 'get', ['id' => $entityFinancialTrxn['values'][$entityFinancialTrxn['id']]['financial_trxn_id']]);
    $this->assertEquals('1234', $financialTrxn['values'][$financialTrxn['id']]['check_number']);
  }

  /**
   * CRM-18000 - Test start_date, end_date after renewal
   *
   * @throws \CRM_Core_Exception
   */
  public function testMembershipRenewalDates(): void {
    foreach ([$this->contactID, $this->contactID2] as $contactID) {
      $membershipParams = [
        'membership_type_id' => $this->membershipTypeID2,
        'contact_id' => $contactID,
        'start_date' => '01/01/2015',
        'join_date' => '01/01/2010',
        'end_date' => '12/31/2015',
      ];
      $this->contactMembershipCreate($membershipParams);
    }

    $params = $this->getMembershipData();
    //ensure membership renewal
    $params['member_option'] = [
      1 => 2,
      2 => 2,
    ];
    $params['field'][1]['membership_type'] = [0 => $this->ids['Contact']['organization_2'], 1 => $this->membershipTypeID2];
    $params['field'][1]['receive_date'] = date('Y-m-d');

    // explicitly specify start and end dates
    $params['field'][2]['membership_type'] = [0 => $this->ids['Contact']['organization_2'], 1 => $this->membershipTypeID2];
    $params['field'][2]['membership_start_date'] = "2016-04-01";
    $params['field'][2]['membership_end_date'] = "2017-03-31";
    $params['field'][2]['receive_date'] = '2016-04-01';
    $this->createTestEntity('Batch', ['name' => 'membership', 'status_id:name' => 'Open', 'type_id:name' => 'Membership', 'item_count' => 3, 'total' => 3]);
    $this->getTestForm('CRM_Batch_Form_Entry', $params, ['id' => $this->ids['Batch']['default']])
      ->processForm();
    $batch = Batch::get()
      ->execute()->single();
    $this->assertEquals(4500, $batch['total']);
    $result = $this->callAPISuccess('Membership', 'get')['values'];

    // renewal dates should be from current if start_date and end_date is passed as NULL
    $this->assertEquals(date('Y-m-d'), $result[1]['start_date']);
    $endDate = date('Y-m-d', strtotime(date("Y-m-d") . " +1 year -1 day"));
    $this->assertEquals($endDate, $result[1]['end_date']);
    $this->assertEquals($params['field'][1]['member_campaign_id'], $result[1]['campaign_id']);

    // verify if the modified dates asserts with the dates passed above
    $this->assertEquals('2016-04-01', $result[2]['start_date']);
    $this->assertEquals('2017-03-31', $result[2]['end_date']);
    $this->assertTrue(empty($result[2]['campaign_id']));
  }

  /**
   * Data provider for test process membership.
   *
   * @return array
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function getMembershipData(): array {
    return [
      'batch_id' => 4,
      'primary_profiles' => [1 => NULL, 2 => NULL, 3 => NULL],
      'primary_contact_id' => [
        1 => $this->contactID,
        2 => $this->contactID2,
        3 => $this->contactID3,
      ],
      'field' => [
        1 => [
          'membership_type' => [0 => $this->organizationContactID, 1 => $this->membershipTypeID],
          'membership_join_date' => '2013-07-22',
          'membership_start_date' => NULL,
          'membership_end_date' => NULL,
          'membership_source' => NULL,
          'financial_type' => 2,
          'total_amount' => $this->formatMoneyInput(1500),
          'receive_date' => '2013-07-24',
          'receive_date_time' => NULL,
          'payment_instrument' => 1,
          'trxn_id' => 'TX101',
          'check_number' => NULL,
          'contribution_status_id' => 1,
          'member_campaign_id' => $this->createCampaign(),
        ],
        2 => [
          'membership_type' => [0 => $this->organizationContactID, 1 => $this->membershipTypeID],
          'membership_join_date' => '2013-07-03',
          'membership_start_date' => '2013-02-03',
          'membership_end_date' => NULL,
          'membership_source' => NULL,
          'financial_type' => 2,
          'total_amount' => $this->formatMoneyInput(1500),
          'receive_date' => '2013-07-17',
          'payment_instrument' => 1,
          'trxn_id' => 'TX102',
          'check_number' => NULL,
          'contribution_status_id' => 1,
        ],
        // no join date, coded end date
        3 => [
          'membership_type' => [0 => $this->organizationContactID, 1 => $this->membershipTypeID],
          'membership_join_date' => NULL,
          'membership_start_date' => NULL,
          'membership_end_date' => '2013-12-01',
          'membership_source' => NULL,
          'financial_type' => 2,
          'total_amount' => $this->formatMoneyInput(1500),
          'receive_date' => '2013-07-17',
          'payment_instrument' => 1,
          'trxn_id' => 'TX103',
          'check_number' => NULL,
          'contribution_status_id' => 1,
        ],

      ],
      'actualBatchTotal' => 0,

    ];
  }

  /**
   * @return array
   */
  public function getContributionData() {
    return [
      //'batch_id' => 4,
      'primary_profiles' => [1 => NULL, 2 => NULL, 3 => NULL],
      'primary_contact_id' => [
        1 => $this->contactID,
        2 => $this->contactID2,
        3 => $this->contactID3,
      ],
      'field' => [
        1 => [
          'financial_type' => 1,
          'total_amount' => $this->formatMoneyInput(1500.15),
          'receive_date' => '2013-07-24',
          'receive_date_time' => NULL,
          'payment_instrument' => 1,
          'check_number' => NULL,
          'contribution_status_id' => 1,
        ],
        2 => [
          'financial_type' => 1,
          'total_amount' => $this->formatMoneyInput(1500.15),
          'receive_date' => '2013-07-24',
          'receive_date_time' => NULL,
          'payment_instrument' => 1,
          'check_number' => NULL,
          'contribution_status_id' => 1,
        ],
        3 => [
          'financial_type' => 3,
          'total_amount' => $this->formatMoneyInput(1500.15),
          'receive_date' => '2013-07-24',
          'receive_date_time' => NULL,
          'payment_instrument' => 4,
          'contribution_check_number' => '1234',
          'contribution_status_id' => 1,
        ],
      ],
      'actualBatchTotal' => $this->formatMoneyInput(4500.45),

    ];
  }

  /**
   * Create a campaign.
   *
   * @return mixed
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  private function createCampaign(): int {
    return (int) Campaign::create()->setValues([
      'name' => 'blah',
      'title' => 'blah',
    ])->execute()->first()['id'];
  }

}
