<?php

/**
 * @file
 * Definition of Drupal\message_ui\Tests\MessageUiPermissions.
 */

namespace Drupal\message_ui\Tests;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\message\Tests\MessageTestBase;
use Drupal\message\Entity\Message;

/**
 * Testing the message access use case.
 *
 * @group Message UI
 */
class MessageUiPermissions extends MessageTestBase {

  /**
   * The user object.
   * @var
   */
  public $user;

  /**
   * The user role.
   * @var
   */
  public $rid;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['message', 'message_ui'];

  public static function getInfo() {
    return array(
      'name' => 'Message UI permissions',
      'description' => 'Testing the use case of message_access function.',
      'group' => 'Message UI',
    );
  }

  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();

    $this->user = $this->drupalCreateUser();

    // Load 'authenticated' user role.
    $this->rid = Role::load(RoleInterface::AUTHENTICATED_ID)->id();

    // Create Message type foo.
    $this->createMessageType('foo', 'Dummy test', 'Example text.', array('Dummy message'));
  }

  /**
   * Test message_access use case.
   */
  function testMessageUiPermissions() {
    // verify the user can't create the message.
    $this->drupalLogin($this->user);

    $this->drupalGet('admin/content/messages/create/foo');
    $this->assertResponse(403, t("The user can't create message."));

    // Create the message.
    $this->grantMessageUiPermission('create');
    $this->drupalPostForm('admin/content/messages/create/foo', array(), t('Create'));

    // Verify the user now can see the text.
    $this->grantMessageUiPermission('view');
    $this->drupalGet('message/1');
    $this->assertResponse(200, "The user can't view message.");

    // Verify can't edit the message.
    $this->drupalGet('message/1/edit');
    $this->assertResponse(403, "The user can't edit message.");

    // Grant permission to the user.
    $this->grantMessageUiPermission('edit');
    $this->drupalGet('message/1/edit');
    $this->assertResponse(200, "The user can't edit message.");

    // Verify the user can't delete the message.
    $this->drupalGet('message/1/delete');
    $this->assertResponse(403, "The user can't delete the message");

    // Grant the permission to the user.
    $this->grantMessageUiPermission('delete');
    $this->drupalPostForm('message/1/delete', array(), t('Delete'));

    // The user did not have the permission to the overview page - verify access
    // denied.
    $this->assertResponse(403, t("The user can't access the over view page."));

    user_role_grant_permissions($this->rid, array('administer message types'));
    $this->drupalGet('admin/content/messages');
    $this->assertResponse(200, "The user can access the over view page.");

    // Create a new user with the bypass access permission and verify the bypass.
    $this->drupalLogout();
    $user = $this->drupalCreateUser(array('bypass message access control'));

    // Verify the user can by pass the message access control.
    $this->drupalLogin($user);
    $this->drupalGet('admin/content/messages/create/foo');
    $this->assertResponse(200, 'The user can bypass the message access control.');
  }

  /**
   * Grant to the user a specific permission.
   *
   * @param $operation
   *  The type of operation - create, update, delete or view.
   */
  private function grantMessageUiPermission($operation) {
    user_role_grant_permissions($this->rid, array($operation . ' foo message'));
  }

  /**
   * Checking the alteration flow for other modules.
   */
  public function testMessageUiAccessHook() {
    // Install the message ui test dummy module.
    \Drupal::service('module_installer')->install(['message_ui_test']);

    $this->drupalLogin($this->user);

    // Setting up the operation and the expected value from the access callback.
    $permissions = array(
      'create' => TRUE,
      'view' => TRUE,
      'delete' => FALSE,
      'update' => FALSE,
    );

    // Get the message type and create an instance.
    $message_type = $this->loadMessageType('foo');
    /* @var $message Message */
    $message = Message::create(array('type' => $message_type->id()));
    $message->setOwner($this->user);
    $message->save();

    foreach ($permissions as $op => $value) {
      // When the hook access of the dummy module will get in action it will
      // check which value need to return. If the access control function will
      // return the expected value then we know the hook got in action.
      $message->{$op} = $value;
      $params = array(
        '@operation' => $op,
        '@value' => $value,
      );

      $access_handler = \Drupal::entityManager()->getAccessControlHandler('message');
      $this->assertEqual($value, $access_handler->access($message, $op, \Drupal::currentUser()), new FormattableMarkup('The hook return @value for @operation', $params));
    }
  }
}
