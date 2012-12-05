<?php
/**
 * Main admin panel for the RMS.
 *
 * The main admin panel allows read/write access to most of the RMS database via a GUI.
 *
 * @author     Russell Toris <rctoris@wpi.edu>
 * @copyright  2012 Russell Toris, Worcester Polytechnic Institute
 * @license    BSD -- see LICENSE file
 * @version    December, 5 2012
 * @package    admin
 * @link       http://ros.org/wiki/rms
 */

// start the session
session_start();

// check if a user is logged in
if (!isset($_SESSION['userid'])) {
  header('Location: login/?goto=admin');
  return;
}

// the name of the admin page
$pagename = 'Admin Panel';

// load the include files
include_once(dirname(__FILE__).'/../api/api.inc.php');
include_once(dirname(__FILE__).'/../api/config/config.inc.php');
include_once(dirname(__FILE__).'/../api/config/javascript_files/javascript_files.inc.php');
include_once(dirname(__FILE__).'/../api/config/logs/logs.inc.php');
include_once(dirname(__FILE__).'/../api/content/articles/articles.inc.php');
include_once(dirname(__FILE__).'/../api/content/content_pages/content_pages.inc.php');
include_once(dirname(__FILE__).'/../api/content/slides/slides.inc.php');
include_once(dirname(__FILE__).'/../api/robot_environments/robot_environments.inc.php');
include_once(dirname(__FILE__).'/../api/robot_environments/environments/environments.inc.php');
include_once(dirname(__FILE__).'/../api/robot_environments/interfaces/interfaces.inc.php');
include_once(dirname(__FILE__).'/../api/robot_environments/widgets/widgets.inc.php');
include_once(dirname(__FILE__).'/../api/users/user_accounts/user_accounts.inc.php');
include_once(dirname(__FILE__).'/../inc/head.inc.php');
include_once(dirname(__FILE__).'/../inc/content.inc.php');

// grab the user info from the database
$session_user = get_user_account_by_id($_SESSION['userid']);

// now make sure this is an admin
if($session_user['type'] !== 'admin') {
  write_to_log('WARNING: '.$session_user['username'].' attempted to access the admin panel.');
  // send the user back to their main menu
  header('Location: /menu');
  return;
}
?>

<!DOCTYPE html>
<html>
<head>
<?php import_head('../')?>
<title><?php echo $title.' :: '.$pagename?>
</title>
<script type="text/javascript" src="../js/jquery/jquery.tablesorter.js"></script>
<script type="text/javascript" src="../js/ros/ros_bundle.min.js"></script>
<script type="text/javascript">
  var script = '';

  /**
   * The start function creates all of the JQuery UI elements and button callbacks.
   */
  function start() {
    // converts a DOM name attribute to an API script path
    var nameToAPIScript = function(name) {
      if(name === 'users') {
        return '../api/users/user_accounts/';
      } else if(name === 'environments') {
        return '../api/robot_environments/environments/';
      } else if(name === 'interfaces') {
        return '../api/robot_environments/interfaces/';
      } else if(name === 'environment-interfaces') {
        return '../api/robot_environments/';
      } else if(name === 'widgets') {
        return '../api/robot_environments/widgets/';
      } else {
        return 'UNKNOWN';
      }
    };

    // create the user menu
    createMenuButtons();

    // create the tabs for the admin page
    $('#admin-tabs').tabs();

    // create the delete icon buttons
    $('.delete').button({
        icons: {primary: "ui-icon-circle-close"},
        text: false
    });

    // delete button callback
    $('.delete').click(function(e) {
      // find the type and ID
      var deleteScript = nameToAPIScript($(e.target).attr('name'));
      var idString = $(e.target).attr('id');
      var id = idString.substring(idString.indexOf('-') + 1);
      // create a confirm dialog
      var confirm = $('#confirm-delete-popup').dialog({
        position: ['center',100],
        draggable: false,
        resizable: false,
        modal: true,
        show: 'blind',
        width: 300,
        buttons: {
          No: function() {
            $(this).dialog('close');
          },
          Yes: function() {
            createModalPageLoading();
            // make a delete request
            $.ajax(deleteScript, {
              data : 'id=' + id,
              type : 'DELETE',
              beforeSend: function (xhr) {
                // authenticate with the header
                xhr.setRequestHeader('RMS-Use-Session', 'true');
              },
              success : function(data){
                // success -- go back to the login page for the correct redirect
                window.location.reload();
              }
            });
          }
        },
        autoOpen: false
      });
      confirm.html('<p><span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 50px 0;"></span>Are you sure you want to delete the selected item? This <b>cannot</b> be reversed.</p>');
      // load the popup
      $('#confirm-delete-popup').dialog("open");
    });

    // create the add icon buttons
    $('.create-new').button({icons: {primary:'ui-icon-plus'}});

    // create the edit icon buttons
    $('.edit').button({
      icons: {primary: 'ui-icon-pencil'},
      text: false
    });

    // creates a popup used to add/edit an entry
    $('#editor-popup').dialog({
      position: ['center', 100],
      autoOpen: false,
      draggable: false,
      resizable: false,
      modal: true,
      show: 'blind',
      width: 700,
      buttons: {
        Cancel: function() {
          $(this).dialog('close');
        }
      }
    });

    // editor button callbacks
    $('.create-new').click(function(e) {createEditor(e);});
    $('.edit').click(function(e) {createEditor(e);});

    // a function to make the correct AJAX call to display an editor
    var createEditor = function(e) {
      var type = $(e.target).attr('name');

      // special case --  Javascript updater
      if(type === 'js-update') {
        var html = '<p>By using this form, you will delete all local ROS Javascript files and download the latest versions.</p>';
        html += '<form action="javascript:updateJSRequest();"><input type="submit" value="Update" /></form>';

        $('#editor-popup').html(html);
        $('#editor-popup').dialog('open');
      } else {
        createModalPageLoading();

        // grab the script name
        script = nameToAPIScript(type);
        var url = script + '?request=editor';

        // now check if we are getting an ID as well
        var idString = $(e.target).attr('id');
        if(idString.indexOf(type + '-') === 0) {
          var id = idString.substring(idString.indexOf('-') + 1);
          url += '&id=' + id;
        }

        // create an AJAX request
        $.ajax(url, {
          type : 'GET',
          beforeSend: function (xhr) {
            // authenticate with the header
            xhr.setRequestHeader('RMS-Use-Session', 'true');
          },
          success : function(data){
            $('#editor-popup').html(data.data.html);
            removeModalPageLoading();
            $('#editor-popup').dialog('open');
          }
        });
      }
    };

    // make the tables sortable
    $('.tablesorter').tablesorter({
      widgets: ['zebra'],
      headers: {
        // disable the first two columns (delete/edit)
        0:{sorter: false},
        1:{sorter: false}
      }
    });

    // creates the preview popup
    $('#preview-popup').dialog({
      position: ['center', 100],
      autoOpen: false,
      draggable: false,
      resizable: false,
      modal: true,
      show: 'blind',
      width: 1050,
      buttons: {
        Close: function() {
          $(this).dialog('close');
        }
      }
    });
  }

  /**
   * A function to set the HTML of the 'preview-popup' div with the given article content and title.
   *
   * @param title {string} the title of the article to preview
   * @param content {string} the HTML article content to preview
   */
  function preview(title, content) {
    // create the HTML
    $('#preview-popup').html('<section id="page"><article><h2>'+title+'</h2><div class="line"></div><div class="clear">'+content+'</div></article></section>');
    // open the dialog
    $('#preview-popup').dialog('open');
  }

  /**
   * The main submit callback for the editor forms. This will make the correct AJAX call for the form.
   */
	function submit() {
     createModalPageLoading();
     // check the password
     if($('#password').val() !== $('#password-confirm').val()) {
       removeModalPageLoading();
       createErrorDialog('ERROR: Passwords do not match.');
     } else {
      // go through each input to build the AJAX request
      var form = $('#editor-popup').find('form');
      var ajaxType = 'POST';
      var putString = '';
      var formData = new FormData();
      form.find(':input').each(function(){
        if($(this).attr('type') !== 'submit' && $(this).attr('name') !== 'password-confirm') {
          if($(this).attr('name') === 'id') {
            ajaxType = 'PUT';
          }

          if($(this).attr('name') !== 'password' || $(this).val() !== '<?php echo $PASSWORD_HOLDER?>') {
  	        if(putString.length > 1) {
              putString += '&';
            }
            putString += $(this).attr('name') + '=' + $(this).val();
            formData.append($(this).attr('name'), $(this).val());
          }
        }
  	  });

      // check if this is a POST or PUT
      var dataToSubmit = formData;
      if(ajaxType === 'PUT') {
        dataToSubmit = putString;
      }

      // create a AJAX request
      $.ajax(script, {
        data : dataToSubmit,
        cache : false,
        contentType : false,
        processData : false,
        type : ajaxType,
        beforeSend: function (xhr) {
          // authenticate with the header
          xhr.setRequestHeader('RMS-Use-Session', 'true');
        },
        success : function(data){
          // success -- go back to the login page for the correct redirect
          window.location.reload();
        },
        error : function(data){
          // display the error
          var response = JSON.parse(data.responseText);
          removeModalPageLoading();
          createErrorDialog(response.msg);
        }
      });
    }
	}

  /**
   * Make an AJAX request to update the Javascript files.
   */
	function updateJSRequest() {
    createModalPageLoading();

    // create a AJAX request
    var formData = new FormData();
    formData.append('request', 'update');
    $.ajax('../api/config/javascript_files/', {
      data : formData,
      cache : false,
      contentType : false,
      processData : false,
      type : 'POST',
      beforeSend: function (xhr) {
        // authenticate with the header
        xhr.setRequestHeader('RMS-Use-Session', 'true');
      },
      success : function(data){
        // success
        window.location.reload();
      },
      error : function(data){
        // display the error
        var response = JSON.parse(data.responseText);
        removeModalPageLoading();
        createErrorDialog(response.msg);
      }
    });
	}
</script>
</head>

<body onload="start()">
<?php create_header($session_user, $pagename, '../')?>
  <section id="page">
    <section>
      <div class="line"></div>
      <article>
        <div class="admin-tabs-container">
          <div id="admin-tabs">
            <ul>
              <li><a href="#users-tab">Manage Users</a>
              </li>
              <li><a href="#site-log-tab">Site Log</a>
              </li>
              <li><a href="#environments-tab">Manage Environments</a>
              </li>
              <li><a href="#pages-tab">Manage Pages</a>
              </li>
              <li><a href="#site-tab">Site Settings</a>
              </li>
              <li><a href="#maintenance-tab">Site Maintenance</a>
              </li>
            </ul>
            <div id="users-tab">
              <div class="center">
                <h3>Users</h3>
              </div>
              <div class="line"></div>
              <table class="tablesorter">
                <thead>
                  <tr>
                    <th></th>
                    <th></th>
                    <th>ID</th>
                    <th>Username</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>E-mail</th>
                    <th>Role</th>
                  </tr>
                  <tr>
                    <td colspan="8"><hr />
                    </td>
                  </tr>
                </thead>
                <tbody>
                <?php
                // populate the table
                $user_accounts = get_user_accounts();
                $num_users = count($user_accounts);
                for ($i = 0; $i < $num_users; $i++) {
                  $cur = $user_accounts[$i];
                  $class = ($i % 2 == 0) ? 'even' : 'odd';?>
                  <tr class="<?php echo $class?>">
                    <td class="delete-cell">
                      <button class="delete" name="users"
                        id="users-<?php echo $cur['userid']?>">Delete</button>
                    </td>
                    <td class="edit-cell">
                      <button class="edit" name="users"
                        id="users-<?php echo $cur['userid']?>">Edit</button>
                    </td>
                    <td class="content-cell"><?php echo $cur['userid']?>
                    </td>
                    <td class="content-cell"><?php echo $cur['username']?>
                    </td>
                    <td class="content-cell"><?php echo $cur['firstname']?>
                    </td>
                    <td class="content-cell"><?php echo $cur['lastname']?>
                    </td>
                    <td class="content-cell"><?php echo $cur['email']?>
                    </td>
                    <td class="content-cell"><?php echo $cur['type']?>
                    </td>
                  </tr>
                  <?php
                }?>
                </tbody>
                <tr>
                  <td colspan="8"><hr />
                  </td>
                </tr>
                <tr>
                  <td colspan="7"></td>
                  <td class="add-cell">
                    <button class="create-new" id="add-users"
                      name="users">Add User</button>
                  </td>
                </tr>
              </table>
            </div>
            <div id="site-log-tab">
              <div class="center">
                <h3>Site Log</h3>
              </div>
              <div class="line"></div>
              <div id="log-container">
                <table class="table-log">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Timestamp</th>
                      <th>URI</th>
                      <th>Address</th>
                      <th>Enrty</th>
                    </tr>
                    <tr>
                      <td colspan="5"><hr />
                      </td>
                    </tr>
                  </thead>
                  <tbody>
                  <?php
                  // populate the table
                  $logs = get_logs();
                  $num_logs = count($logs);
                  for ($i = 0; $i < $num_logs; $i++) {
                    $cur = $logs[$i];
                    $class = ($i % 2 == 0) ? 'even' : 'odd';?>
                    <tr class="<?php echo $class?>">
                      <td><?php echo $cur['logid']?>
                      </td>
                      <td class="content-cell"><?php echo $cur['timestamp']?>
                      </td>
                      <td class="content-cell"><?php echo $cur['uri']?>
                      </td>
                      <td class="content-cell"><?php echo $cur['addr']?>
                      </td>
                      <td class="content-cell"><?php echo $cur['entry']?>
                      </td>
                    </tr>
                    <?php
                  }?>
                  </tbody>
                </table>
              </div>
            </div>
            <div id="environments-tab">
              <div class="center">
                <h3>Environments</h3>
              </div>
              <div class="line"></div>
              <table class="tablesorter">
                <thead>
                  <tr>
                    <th></th>
                    <th></th>
                    <th>ID</th>
                    <th>Address</th>
                    <th>Type</th>
                    <th>Notes</th>
                    <th>Status</th>
                  </tr>
                  <tr>
                    <td colspan="7"><hr />
                    </td>
                  </tr>
                </thead>
                <tbody>
                <?php
                // populate the table
                $environments = get_environments();
                $num_environments = count($environments);
                for ($i = 0; $i < $num_environments; $i++) {
                  $cur = $environments[$i];
                  $class = ($i % 2 == 0) ? 'even' : 'odd';?>
                  <tr class="<?php echo $class?>">
                    <td class="delete-cell">
                      <button class="delete" name="environments"
                        id="environments-<?php echo $cur['envid']?>">Delete</button>
                    </td>
                    <td class="edit-cell"><button class="edit"
                        name="environments"
                        id="environments-<?php echo $cur['envid']?>">Edit</button>
                    </td>
                    <td class="content-cell"><?php echo $cur['envid']?>
                    </td>
                    <td class="content-cell"><?php echo $cur['envaddr']?>
                    </td>
                    <td class="content-cell"><?php echo $cur['type']?>
                    </td>
                    <td class="content-cell"><?php echo $cur['notes']?>
                    </td>
                    <?php
                    if($cur['enabled']) {// check if the environment is enabled?
                      echo '<script type="text/javascript">
                                rosonline(\''.$cur['envaddr'].'\', 9090, function(isonline) {
                                  if(isonline) {
                                    $(\'#envstatus-'.$cur['envid'].'\').html(\'ONLINE\');
                                  } else {
                                    $(\'#envstatus-'.$cur['envid'].'\').html(\'OFFLINE\');
                                  }
                                });
                              </script>';?>
                    <td class="content-cell"><div
                        id="envstatus-<?php echo $cur['envid']?>">Acquiring
                        connection...</div>
                    </td>
                    <?php
                    } else {?>
                    <td class="content-cell"><div
                        id="envstatus-<?php echo $cur['envid']?>">DISABLED</div>
                    </td>
                    <?php
                    }?>
                  </tr>
                  <?php
                }?>
                </tbody>
                <tr>
                  <td colspan="7"><hr />
                  </td>
                </tr>
                <tr>
                  <td colspan="6"></td>
                  <td class="add-cell">
                    <button class="create-new" id="add-environments"
                      name="environments">Add Environment</button>
                  </td>
                </tr>
              </table>
              <br /> <br />
              <div class="center">
                <h3>Interface</h3>
              </div>
              <div class="line"></div>
              <table class="tablesorter">
                <thead>
                  <tr>
                    <th></th>
                    <th></th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Directory</th>
                  </tr>
                  <tr>
                    <td colspan="5"><hr />
                    </td>
                  </tr>
                </thead>
                <tbody>
                <?php
                // populate the table
                $interfaces = get_interfaces();
                $num_interfaces = count($interfaces);
                for ($i = 0; $i < $num_interfaces; $i++) {
                  $cur = $interfaces[$i];
                  $class = ($i % 2 == 0) ? 'even' : 'odd';?>
                  <tr class="<?php echo $class?>">
                    <td class="delete-cell"><button class="delete"
                        name="interfaces"
                        id="interfaces-<?php echo $cur['intid']?>">Delete</button>
                    </td>
                    <td class="edit-cell"><button class="edit"
                        name="interfaces"
                        id="interfaces-<?php echo $cur['intid']?>">Edit</button>
                    </td>
                    <td class="content-cell"><?php echo $cur['intid']?>
                    </td>
                    <td class="content-cell"><?php echo $cur['name']?>
                    </td>
                    <td class="content-cell"><?php echo $cur['location']?>
                    </td>
                  </tr>
                  <?php
                }?>
                </tbody>
                <tr>
                  <td colspan="5"><hr />
                  </td>
                </tr>
                <tr>
                  <td colspan="4"></td>
                  <td class="add-cell">
                    <button class="create-new" id="add-interface"
                      name="interfaces">Add Interface</button>
                  </td>
                </tr>
              </table>
              <br /> <br />
              <div class="center">
                <h3>Environment-Interface Pairings</h3>
              </div>
              <div class="line"></div>
              <table class="tablesorter">
                <thead>
                  <tr>
                    <th></th>
                    <th></th>
                    <th>ID</th>
                    <th>Environment ID</th>
                    <th>Interface ID</th>
                  </tr>
                  <tr>
                    <td colspan="5"><hr />
                    </td>
                  </tr>
                </thead>
                <tbody>
                <?php
                // populate the table
                $pairs = get_environment_interface_pairs();
                $num_pairs = count($pairs);
                for ($i = 0; $i < $num_pairs; $i++) {
                  $cur = $pairs[$i];
                  $class = ($i % 2 == 0) ? 'even' : 'odd';
                  // grab the interface and environment variables
                  $env = get_environment_by_id($cur['envid']);
                  $int = get_interface_by_id($cur['intid']);
                  ?>
                  <tr class="<?php echo $class?>">
                    <td class="delete-cell"><button class="delete"
                        name="environment-interfaces"
                        id="environment-interfaces-<?php echo $cur['pairid']?>">Delete</button>
                    </td>
                    <td class="edit-cell"><button class="edit"
                        name="environment-interfaces"
                        id="environment-interfaces-<?php echo $cur['pairid']?>">Edit</button>
                    </td>
                    <td class="content-cell"><?php echo $cur['pairid']?>
                    </td>
                    <td class="content-cell"><?php echo $env['envid'].': '.$env['envaddr'].
                                                           ' -- '.$env['type'].' :: '.$env['notes']?>
                    </td>
                    <td class="content-cell"><?php echo $int['intid'].': '.$int['name']?>
                    </td>
                  </tr>
                  <?php
                }?>
                </tbody>
                <tr>
                  <td colspan="5"><hr />
                  </td>
                </tr>
                <tr>
                  <td colspan="4"></td>
                  <td class="add-cell">
                    <button class="create-new"
                      id="add-environment-interfaces"
                      name="environment-interfaces">Add Pairing</button>
                  </td>
                </tr>
              </table>
              <br /> <br />
              <div class="center">
                <h3>Widgets</h3>
              </div>
              <div class="line"></div>
              <table class="tablesorter">
                <thead>
                  <tr>
                    <th></th>
                    <th></th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>SQL Table</th>
                    <th>PHP Script</th>
                  </tr>
                  <tr>
                    <td colspan="6"><hr />
                    </td>
                  </tr>
                </thead>
                <tbody>
                <?php
                // populate the table
                $widgets = get_widgets();
                $num_widgets = count($widgets);
                for ($i = 0; $i < $num_widgets; $i++) {
                  $cur = $widgets[$i];
                  $class = ($i % 2 == 0) ? 'even' : 'odd';?>
                  <tr class="<?php echo $class?>">
                    <td class="delete-cell"><button class="delete"
                        name="widgets"
                        id="widgets-<?php echo $cur['widgetid']?>">Delete</button>
                    </td>
                    <td class="edit-cell"><button class="edit"
                        name="widgets"
                        id="widgets-<?php echo $cur['widgetid']?>">Edit</button>
                    </td>
                    <td class="content-cell"><?php echo $cur['widgetid']?>

                    </td>
                    <td class="content-cell"><a
                      href="#widget<?php echo $cur['widgetid']?>"><?php echo $cur['name']?>
                    </a>
                    </td>
                    <td class="content-cell"><?php echo $cur['table']?>

                    </td>
                    <td class="content-cell"><?php echo $cur['script']?>

                    </td>
                  </tr>
                  <?php
                }?>
                </tbody>
                <tr>
                  <td colspan="6"><hr />
                  </td>
                </tr>
                <tr>
                  <td colspan="5"></td>
                  <td class="add-cell">
                    <button class="create-new" id="add-widget"
                      name="widgets">Add Widget</button>
                  </td>
                </tr>
              </table>
              <?php
              // individual widget tables
              foreach ($widgets as $w) {?>
              <br /> <br />
              <div class="center">
                <h3 id="widget<?php echo $w['widgetid']?>">
                <?php echo $w['name']?>
                </h3>
              </div>
              <div class="line"></div>
              <table class="tablesorter">
                <thead>
                <?php
                // build an array of the column names
                $attributes = get_widget_table_columns_by_id($w['widgetid']);
                $num_att = count($attributes);?>
                  <tr>
                    <th></th>
                    <th></th>
                    <?php
                    foreach ($attributes as $label) {
                      echo '<th>'.$label.'</th>';
                    }?>
                  </tr>
                  <tr>
                    <td colspan="<?php echo ($num_att+2)?>"><hr />
                    </td>
                  </tr>
                </thead>
                <tbody>
                <?php
                // populate the table
                $instances = get_widget_instances_by_id($w['widgetid']);
                $num_instances = count($instances);
                for ($i = 0; $i < $num_instances; $i++) {
                  $cur = $instances[$i];
                  $class = ($i % 2 == 0) ? 'even' : 'odd';?>
                  <tr class="<?php echo $class?>">
                    <td class="delete-cell"><button class="delete"
                        name="widget-<?php echo $w['widgetid']?>"
                        id="widget-<?php echo $w['widgetid'].'-'.$cur['id']?>">Delete</button>
                    </td>
                    <td class="edit-cell"><button class="edit"
                        name="widget-<?php echo $w['widgetid']?>"
                        id="widget-<?php echo $w['widgetid'].'-'.$cur['id']?>">Edit</button>
                    </td>
                    <?php foreach ($attributes as $label) {?>
                    <td class="content-cell"><?php echo $cur[$label]?>
                    </td>
                    <?php
                    }?>
                  </tr>
                  <?php
                }?>
                </tbody>
                <tr>
                  <td colspan="<?php echo ($num_att+2)?>"><hr />
                  </td>
                </tr>
                <tr>
                  <td colspan="<?php echo ($num_att+1)?>"></td>
                  <td class="add-cell">
                    <button class="create-new"
                      id="add-widget-<?php echo $w['widgetid']?>"
                      name="widget-<?php echo $w['widgetid']?>">
                      Add
                      <?php echo $w['name']?>
                    </button>
                  </td>
                </tr>
              </table>
              <?php
              }?>
            </div>
            <div id="pages-tab">
              <div id="slideshow">
                <div class="center">
                  <h3>Homepage Slideshow</h3>
                </div>
                <div class="line"></div>
                <table class="tablesorter">
                  <thead>
                    <tr>
                      <th></th>
                      <th></th>
                      <th>ID</th>
                      <th>Image</th>
                      <th>Caption</th>
                      <th>Index</th>
                    </tr>
                    <tr>
                      <td colspan="6"><hr />
                      </td>
                    </tr>
                  </thead>
                  <tbody>
                  <?php
                  // populate the table
                  $slides = get_slides();
                  $num_slides = count($slides);
                  for ($i = 0; $i < $num_slides; $i++) {
                    $cur = $slides[$i];
                    $class = ($i % 2 == 0) ? 'even' : 'odd';?>
                    <tr class="<?php echo $class?>">
                      <td class="delete-cell"><div
                          id="<?php echo $cur['slideid']?>"
                          class="delete">
                          <button>Delete</button>
                        </div>
                      </td>
                      <td class="edit-cell"><div
                          id="<?php echo $cur['slideid']?>" class="edit">
                          <button>Edit</button>
                        </div>
                      </td>
                      <td class="content-cell"><?php echo $cur['slideid']?>
                      </td>
                      <td class="content-cell"><?php echo $cur['img']?>
                      </td>
                      <td class="content-cell"><?php echo $cur['caption']?>
                      </td>
                      <td class="content-cell"><?php echo $cur['index']?>
                      </td>
                    </tr>
                    <?php
                  }?>
                  </tbody>
                  <tr>
                    <td colspan="6"><hr />
                    </td>
                  </tr>
                  <tr>
                    <td colspan="5"></td>
                    <td class="add-cell">
                      <div class="add">
                        <button class="editor" id="add-article">Add
                          Slide</button>
                      </div>
                    </td>
                  </tr>
                </table>
              </div>
              <div id="pages">
                <br /> <br />
                <div class="center">
                  <h3>Content Pages</h3>
                </div>
                <div class="line"></div>
                <table class="tablesorter">
                  <thead>
                    <tr>
                      <th></th>
                      <th></th>
                      <th>ID</th>
                      <th>Title</th>
                      <th>Menu Name</th>
                      <th>Menu Index</th>
                      <th>Javascript File</th>
                    </tr>
                    <tr>
                      <td colspan="7"><hr />
                      </td>
                    </tr>
                  </thead>
                  <tbody>
                  <?php
                  // populate the table
                  $pages = get_content_pages();
                  $num_pages = count($pages);
                  for ($i = 0; $i < $num_pages; $i++) {
                    $cur = $pages[$i];
                    $class = ($i % 2 == 0) ? 'even' : 'odd';?>
                    <tr class="<?php echo $class?>">
                      <td class="delete-cell"><div
                          id="<?php echo $cur['pageid']?>"
                          class="delete">
                          <button>Delete</button>
                        </div>
                      </td>
                      <td class="edit-cell"><div
                          id="<?php echo $cur['pageid']?>" class="edit">
                          <button>Edit</button>
                        </div>
                      </td>
                      <td class="content-cell"><?php echo $cur['pageid']?>
                      </td>
                      <td class="content-cell"><?php echo $cur['title']?>
                      </td>
                      <td class="content-cell"><?php echo $cur['menu_name']?>
                      </td>
                      <td class="content-cell"><?php echo $cur['menu_index']?>
                      </td>
                      <?php
                      if($cur['js']) {
                        echo '<td class="content-cell">'.$cur['js'].'</td>';
                      } else {
                        echo '<td class="content-cell">---</td>';
                      }?>
                    </tr>
                    <?php
                  }?>
                  </tbody>
                  <tr>
                    <td colspan="7"><hr />
                    </td>
                  </tr>
                  <tr>
                    <td colspan="6"></td>
                    <td class="add-cell">
                      <div class="add">
                        <button class="editor" id="add-page">Add Page</button>
                      </div>
                    </td>
                  </tr>
                </table>
              </div>
              <div id="articles">
                <br /> <br />
                <div class="center">
                  <h3>Content Page Articles</h3>
                </div>
                <div class="line"></div>
                <table class="tablesorter">
                  <thead>
                    <tr>
                      <th></th>
                      <th></th>
                      <th>ID</th>
                      <th>Title</th>
                      <th>Content</th>
                      <th>Page</th>
                      <th>Page-Index</th>
                    </tr>
                    <tr>
                      <td colspan="7"><hr />
                      </td>
                    </tr>
                  </thead>
                  <tbody>
                  <?php
                  // populate the table
                  $articles = get_articles();
                  $num_articles = count($articles);
                  for ($i = 0; $i < $num_articles; $i++) {
                    $cur = $articles[$i];
                    $class = ($i % 2 == 0) ? 'even' : 'odd';?>
                    <tr class="<?php echo $class?>">
                      <td class="delete-cell"><div
                          id="<?php echo $cur['artid']?>" class="delete">
                          <button>Delete</button>
                        </div>
                      </td>
                      <td class="edit-cell"><div
                          id="<?php echo $cur['artid']?>" class="edit">
                          <button>Edit</button>
                        </div>
                      </td>
                      <td class="content-cell"><?php echo $cur['artid']?>
                      </td>
                      <td class="content-cell"><?php echo $cur['title']?>
                      </td>
                      <?php
                      // check if we should trim the string to fit in the table
                      if(strlen(htmlentities($cur['content'])) > 30) {
                        echo '<td class="content-cell">'.substr(htmlentities($cur['content']), 0, 30).'...</td>';
                      } else {
                        echo '<td class="content-cell">'.htmlentities($cur['content']).'</td>';
                      }
                      // grab the page name from the database
                      $res = get_content_page_by_id($cur['pageid']);?>
                      <td class="content-cell"><?php echo $res['title']?>
                      </td>
                      <td class="content-cell"><?php echo $cur['pageindex']?>
                      </td>
                    </tr>
                    <?php
                  }?>
                  </tbody>
                  <tr>
                    <td colspan="7"><hr />
                    </td>
                  </tr>
                  <tr>
                    <td colspan="6"></td>
                    <td class="add-cell">
                      <div class="add">
                        <button class="editor" id="add-article">Add
                          Article</button>
                      </div>
                    </td>
                  </tr>
                </table>
              </div>
            </div>
            <div id="site-tab">
              <div id="site">
                <div class="center">
                  <h3>Site Settings</h3>
                </div>
                <div class=line></div>
                <table>
                  <tbody>
                    <tr>
                      <td width="33%" rowspan="7"></td>
                      <td class="setting-label">Database Host:</td>
                      <td><?php echo $dbhost?>
                      </td>
                      <td width="33%" rowspan="7"></td>
                    </tr>
                    <tr>
                      <td class="setting-label">Database Name:</td>
                      <td><?php echo $dbname?>
                      </td>
                    </tr>
                    <tr>
                      <td class="setting-label">Database Username:</td>
                      <td><?php echo $dbuser?>
                      </td>
                    </tr>
                    <tr>
                      <td class=setting-label>Database Password:</td>
                      <td><?php echo $PASSWORD_HOLDER?>
                      </td>
                    </tr>
                    <tr>
                      <td class="setting-label">Site Name:</td>
                      <td><?php echo $title?>
                      </td>
                    </tr>
                    <tr>
                      <td class="setting-label">Google Analytics
                        Tracking ID:</td>
                      <td><?php echo (isset($google_tracking_id)) ? $google_tracking_id : '---'?>
                      </td>
                    </tr>
                    <tr>
                      <td class="setting-label">Copyright Message:</td>
                      <td><?php echo substr($copyright, 6)?>
                      </td>
                    </tr>
                  </tbody>
                  <tr>
                    <td colspan="4"><hr />
                    </td>
                  </tr>
                  <tr>
                    <td colspan="3"></td>
                    <td class="add-cell">
                      <div class="add">
                        <button class="editor" id="add-site">Edit
                          Settings</button>
                      </div>
                    </td>
                  </tr>
                </table>
              </div>
            </div>
            <div id="maintenance-tab">
              <div id="site-status">
                <div class="center">
                  <h3>Site Status</h3>
                </div>
                <div class=line></div>
                <?php
                // grab the database version
                $db_version = get_db_version();
                // grab the code version
                $prot = (isset($_SERVER['HTTPS'])) ? 'https://' : 'http://';
                $code_version = get_init_sql_version($prot.$_SERVER['HTTP_HOST'].'/api/config/init.sql');
                // find our the live version
                $live_version = get_init_sql_version("https://raw.github.com/WPI-RAIL/rms/fuerte-devel/www/admin/init.sql");

                // check if an update is needed
                $disable = ($db_version < $live_version) ? '' : 'disabled="disabled"';
                ?>

                <table>
                  <tbody>
                    <tr>
                      <td width="33%" rowspan="2"></td>
                      <td class="setting-label">Database Version:</td>
                      <td><?php echo $db_version?>
                      </td>
                      <td width="33%" rowspan="2"></td>
                    </tr>
                    <tr>
                      <td class="setting-label">Code Version:</td>
                      <td><?php echo $code_version?>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="4"><hr />
                      </td>
                    </tr>
                    <tr>
                      <td width="33%" rowspan="1"></td>
                      <td class="setting-label">Released Version:</td>
                      <td><?php echo $live_version?>
                      </td>
                      <td width="33%" rowspan="1"></td>
                    </tr>
                  </tbody>
                  <tr>
                    <td colspan="4"><hr />
                    </td>
                  </tr>
                  <tr>
                    <td colspan="3"></td>
                    <td class="add-cell">
                      <div class="add">
                        <button class="editor" id="update-db"
                        <?php echo $disable?>>Run Database Update</button>
                      </div>
                    </td>
                  </tr>
                </table>
              </div>
              <div id="javascript">
                <div class="center">
                  <h3>ROS Javascript</h3>
                </div>
                <div class=line></div>
                <?php
                // grab the Javascript file list
                $js_files = get_javascript_files();
                ?>
                <table>
                  <tbody>
                  <?php
                  foreach ($js_files as $file) {?>
                    <tr>
                      <td width="33%"></td>
                      <td class=setting-label><?php echo $file['path']?>:</td>
                      <td><?php echo file_exists(dirname(__FILE__).'/../'.$file['path']) ? 'Exists' : '<b>MISSING</b>'?>
                      </td>
                      <td width="33%"></td>
                    </tr>
                    <?php
                  }?>
                  </tbody>
                  <tr>
                    <td colspan="4"><hr />
                    </td>
                  </tr>
                  <tr>
                    <td colspan="3"></td>
                    <td class="add-cell">
                      <button class="create-new" id="js-update"
                        name="js-update">Update ROS Javascript</button>
                    </td>
                  </tr>
                </table>
              </div>
              <div id="privileges">
                <div class="center">
                  <h3>Directory Privileges</h3>
                </div>
                <div class=line></div>
                <table>
                  <tbody>
                    <tr>
                      <td width="33%" rowspan="4"></td>
                      <td class=setting-label>js/ros:</td>
                      <td><?php echo is_writable(dirname(__FILE__).'/../js/ros') ? 'Writable' : '<b>UN-WRITABLE</b>'?>
                      </td>
                      <td width="33%" rowspan="4"></td>
                    </tr>
                    <tr>
                      <td class=setting-label>js/ros/widgets:</td>
                      <td><?php echo is_writable(dirname(__FILE__).'/../js/ros/widgets') ? 'Writable' : '<b>UN-WRITABLE</b>'?>
                      </td>
                    </tr>
                    <tr>
                      <td class=setting-label>inc:</td>
                      <td><?php echo is_writable(dirname(__FILE__).'/../inc') ? 'Writable' : '<b>UN-WRITABLE</b>'?>
                      </td>
                    </tr>
                    <tr>
                      <td class=setting-label>img/slides:</td>
                      <td><?php echo is_writable(dirname(__FILE__).'/../img/slides') ? 'Writable' : '<b>UN-WRITABLE</b>'?>
                      </td>
                    </tr>
                  </tbody>
                  <tr>
                    <td colspan="4"><hr />
                    </td>
                  </tr>
                </table>
              </div>
            </div>
          </div>
        </div>
      </article>
    </section>
    <?php create_footer()?>
  </section>

  <div id="confirm-delete-popup" title="Delete?"></div>
  <div id="editor-popup" title="Editor"></div>
  <div id="preview-popup" title="Content Preview"></div>

</html>
