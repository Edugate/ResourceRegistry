<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * @package   Jagger
 * @author    Middleware Team HEAnet
 * @author    Janusz Ulanowski <janusz.ulanowski@heanet.ie>
 * @copyright 2016, HEAnet Limited (http://www.heanet.ie)
 * @license   MIT http://www.opensource.org/licenses/mit-license.php
 */
class Entitystate extends MY_Controller
{

    protected $id;
    protected $tmpProviders;
    /**
     * @var models\Provider|null $entity
     */
    protected $entity;

    public function __construct() {
        parent::__construct();

        $this->tmpProviders = new models\Providers;
        $this->load->library(array('formelement', 'form_validation', 'metadatavalidator', 'zacl'));
        $this->tmpProviders = new models\Providers();
        $this->entity = null;
    }

    private function validateAccess() {
        $result = array(
            'access' => true,
            'code' => 200,
            'msg' => 'OK'
        );
        if (!$this->jauth->isLoggedIn()) {
            $result = array(
                'access' => false,
                'code' => 401,
                'msg' => 'Access denied - not authenticated'
            );
        };
        if (!$this->input->is_ajax_request()) {
            $result = array(
                'access' => false,
                'code' => 401,
                'msg' => 'Access denied'
            );
        };
        $updateData = $this->input->post('updatedata');
        if (empty($updateData)) {
            $result = array(
                'access' => false,
                'code' => 401,
                'msg' => 'missing input'
            );
        };

        return $result;
    }


    public function updatemembership() {
        $validation = $this->validateAccess();
        if ($validation['access'] === false) {
            return $this->output->set_status_header($validation['code'])->set_output($validation['msg']);
        }
        $inputInArray = explode('|', $this->input->post('updatedata'));
        if (count($inputInArray) != 4) {
            return $this->output->set_status_header(401)->set_output('incorrect input');
        }
        $entID = $inputInArray[0];
        $fedID = $inputInArray[1];
        $action = $inputInArray[2];
        $state = $inputInArray[3];

        $isValid = (bool)(ctype_digit($entID) && ctype_digit($fedID) && in_array($action, array('ban', 'dis'), true) && ctype_digit($state));
        if (!$isValid) {
            return $this->output->set_status_header(401)->set_output('wrong  input');
        }
        if ($action === 'ban' && !$this->jauth->isAdministrator()) {
            return $this->output->set_status_header(401)->set_output('Access denied');
        }

        /**
         * @var models\FederationMembers $fedMembership
         */
        $fedMembership = $this->em->getRepository('models\FederationMembers')->findOneBy(array('provider' => $entID, 'federation' => $fedID));
        if ($fedMembership === null) {
            return $this->output->set_status_header(404)->set_output('Memebrship not found');
        }

        $hasManageAccess = $this->zacl->check_acl($fedMembership->getProvider()->getId(), 'manage', 'entity', '');
        if (!$hasManageAccess) {
            return $this->output->set_status_header(401)->set_output('Access denied');
        }
        $boolState = (bool)$state;
        $currBanned = $fedMembership->isBanned();
        $currDisabled = $fedMembership->isDisabled();
        if ($action === 'ban' && ($currBanned !== $boolState)) {
            $fedMembership->setBanned($boolState);
            $this->tracker->save_track(strtolower($fedMembership->getProvider()->getType()), 'membership', $fedMembership->getProvider()->getEntityId(), 'membership with ' . $fedMembership->getFederation()->getName() . ' administratively changed to: ' . $fedMembership->isBannedToStr(), false);
            $this->emailsender->membershipStateChanged($fedMembership);
        } elseif ($action === 'dis' && ($currDisabled !== $boolState)) {
            $fedMembership->setDisabled($boolState);
            $this->tracker->save_track(strtolower($fedMembership->getProvider()->getType()), 'membership', $fedMembership->getProvider()->getEntityId(), 'membership with ' . $fedMembership->getFederation()->getName() . ' changed to: ' . $fedMembership->isBannedToStr(), false);
            $this->emailsender->membershipStateChanged($fedMembership);
        }
        $this->em->persist($fedMembership);

        try {
            $this->em->flush();

        } catch (Exception $e) {
            log_message('error', __METHOD__ . ' ' . $e);

            return $this->output->set_status_header(500)->set_output('unkwonn problem');
        }

        return $this->output->set_content_type('application/json')->set_output(json_encode(array('message' => lang('membershibupdated') . '. ' . lang('needrefreshpage'))));


    }

    private function _submit_validate() {
        $this->form_validation->set_rules('elock', lang('rr_lock_entity'), 'max_length[1]');
        $this->form_validation->set_rules('eactive', lang('rr_entityactive'), 'max_length[1]');
        $this->form_validation->set_rules('extint', lang('rr_entitylocalext'), 'max_length[1]');
        $this->form_validation->set_rules('publicvisible', 'public visible', 'max_length[1]');
        $this->form_validation->set_rules('validuntiltime', 'time until', 'trim|valid_time_hhmm');
        $this->form_validation->set_rules('validfromtime', 'Valid from time', 'trim|valid_time_hhmm');
        $this->form_validation->set_rules('validfromdate', 'Valid from date', 'trim|valid_date');
        $this->form_validation->set_rules('validuntildate', 'Valid until date', 'trim|valid_date');

        return $this->form_validation->run();
    }


    public function regpolicies($id) {
        if (!$this->jauth->isLoggedIn()) {
            redirect('auth/login', 'location');
        }
        if (!ctype_digit($id)) {
            show_error('Incorrect entity id provided', 404);
        }

        $this->entity = $this->tmpProviders->getOneById($id);

        if (null === $this->entity) {
            show_error('Provider not found', 404);
        }
        $type = $this->entity->getType();
        if ($type === 'IDP') {
            $data['titlepage'] = lang('identityprovider') . ':';
            $plist = array('url' => base_url('providers/idp_list/showlist'), 'name' => lang('identityproviders'));
        } elseif ($type === 'SP') {
            $data['titlepage'] = lang('serviceprovider') . ':';
            $plist = array('url' => base_url('providers/sp_list/showlist'), 'name' => lang('serviceproviders'));
        } else {
            $data['titlepage'] = '';
            $plist = array('url' => base_url('providers/idp_list/showlist'), 'name' => lang('identityproviders'));
        }
        $myLang = MY_Controller::getLang();
        $isLocked = $this->entity->getLocked();
        $isLocal = $this->entity->getLocal();
        $titlename = $this->entity->getNameToWebInLang($myLang, $this->entity->getType());
        $data['titlepage'] .= ' <a href="' . base_url() . 'providers/detail/show/' . $this->entity->getId() . '">' . $titlename . '</a>';
        $data['subtitlepage'] = lang('title_regpols');
        $data['providerid'] = $this->entity->getId();
        $hasWriteAccess = $this->zacl->check_acl($this->entity->getId(), 'write', 'entity', '');
        $data['breadcrumbs'] = array(
            $plist,
            array('url' => base_url('providers/detail/show/' . $this->entity->getId() . ''), 'name' => '' . html_escape($titlename) . ''),
            array('url' => '#', 'name' => lang('title_regpols'), 'type' => 'current'),
        );

        if (!$hasWriteAccess) {
            show_error('No sufficient permision to edit entity', 403);
        }
        if ($isLocked) {
            show_error('entity id locked', 403);
        }
        if (!$isLocal) {
            show_error('external entity, cannot be modified', 403);
        }

        $isAdmin = $this->jauth->isAdministrator();

        if (!$_POST) {
            $data['r'] = $this->formelement->NgenerateRegistrationPolicies($this->entity);
            $data['content_view'] = 'manage/entityedit_regpolicies';

            return $this->load->view(MY_Controller::$page, $data);
        }

        $p = $this->input->post('entregpolform');
        if (!empty($p) && strcmp($p, $this->entity->getId()) == 0) {
            $this->load->library('providerupdater');
            $process['regpol'] = array();
            $input = $this->input->post('f');
            if (!empty($input) && isset($input['regpol'])) {
                foreach ($input['regpol'] as $p => $v) {
                    foreach ($v as $k => $l) {
                        $process['regpol'][] = $l;
                    }
                }
            }
            $this->load->library('approval');
            $this->providerupdater->updateRegPolicies($this->entity, $process, $isAdmin);
            try {
                $this->em->flush();
                $data['content_view'] = 'manage/entityedit_regpolicies_success';
                if ($isAdmin) {
                    $this->globalnotices[] = lang('updated');
                } elseif (count($this->globalnotices) == 0) {
                    $this->globalnotices[] = lang('requestsentforapproval');
                }
                $this->load->view(MY_Controller::$page, $data);
            } catch (Exception $e) {
                log_message('error', __METHOD__ . ' ' . $e);
                show_error('Internal server error', 500);
            }
        }

    }


    public function modify($id) {
        if (!$this->jauth->isLoggedIn()) {
            redirect('auth/login', 'location');
        }
        if (!ctype_digit($id)) {
            show_error('Incorrect entity id provided', 404);
        }
        $this->entity = $this->tmpProviders->getOneById($id);

        if (null === $this->entity) {
            show_error('Provider not found', 404);
        }
        $type = $this->entity->getType();
        if (strcasecmp($type, 'SP') == 0) {
            $titleprefix = lang('serviceprovider');
        } elseif (strcasecmp($type, 'IDP') == 0) {
            $titleprefix = lang('identityprovider');
        } else {
            $titleprefix = '';
        }
        $lang = MY_Controller::getLang();
        $titlename = $this->entity->getNameToWebInLang($lang, $type);
        $data = array(
            'titlepage' => $titleprefix . ': <a href="' . base_url() . 'providers/detail/show/' . $this->entity->getId() . '">' . $titlename . '</a>',
            'subtitlepage' => lang('rr_status_mngmt'),
            'entid' => $id,
            'current_locked' => $this->entity->getLocked(),
            'current_active' => $this->entity->getActive(),
            'current_extint' => $this->entity->getLocal(),
            'current_publicvisible' => (int)$this->entity->getPublicVisible()
        );

        if (strcasecmp($this->entity->getType(), 'SP') == 0) {
            $plist = array('url' => base_url('providers/sp_list/showlist'), 'name' => lang('serviceproviders'));
        } else {
            $plist = array('url' => base_url('providers/idp_list/showlist'), 'name' => lang('identityproviders'));
        }
        $data['breadcrumbs'] = array(
            $plist,
            array('url' => base_url('providers/detail/show/' . $this->entity->getId() . ''), 'name' => '' . html_escape($titlename) . ''),
            array('url' => '#', 'name' => lang('rr_status_mngmt'), 'type' => 'current'),


        );
        $validfrom = $this->entity->getValidFrom();
        if (!empty($validfrom)) {
            $validfromdate = date('Y-m-d', $validfrom->format('U'));
            $validfromtime = date('H:i', $validfrom->format('U'));
        } else {
            $validfromdate = '';
            $validfromtime = '';
        }
        $validuntil = $this->entity->getValidTo();
        if (!empty($validuntil)) {
            $validuntildate = date('Y-m-d', $validuntil->format('U'));
            $validuntiltime = date('H:i', $validuntil->format('U'));
        } else {
            $validuntildate = '';
            $validuntiltime = '';
        }
        $data['current_validuntildate'] = $validuntildate;
        $data['current_validuntiltime'] = $validuntiltime;
        $data['current_validfromdate'] = $validfromdate;
        $data['current_validfromtime'] = $validfromtime;
        $has_manage_access = $this->zacl->check_acl($this->entity->getId(), 'manage', 'entity', '');
        if (!$has_manage_access) {
            show_error('No sufficient permision to manage entity', 403);
        }


        if ($this->_submit_validate() === true) {
            $locked = $this->input->post('elock');
            $active = $this->input->post('eactive');
            $extint = $this->input->post('extint');
            $publicvisible = $this->input->post('publicvisible');
            $validfromdate = $this->input->post('validfromdate');
            $validfromtime = $this->input->post('validfromtime');
            $validuntildate = $this->input->post('validuntildate');
            $validuntiltime = $this->input->post('validuntiltime');
            $differ = array();
            if (null !== $locked) {
                if ($data['current_locked'] !== $locked) {

                    if ($locked === '1') {
                        $differ['Lock'] = array('before' => 'unlocked', 'after' => 'locked');
                        $this->entity->Lock();
                    } elseif ($locked === '0') {
                        $this->entity->Unlock();
                        $differ['Lock'] = array('before' => 'locked', 'after' => 'unlocked');
                    }
                }
            }
            if (null !== $active) {
                if ($data['current_active'] !== $active) {
                    if ($active === '1') {
                        $this->entity->Activate();
                        $differ['Active'] = array('before' => 'disabled', 'after' => 'enabled');
                    } elseif ($active === '0') {
                        $this->entity->Disactivate();
                        $differ['Active'] = array('before' => 'enabled', 'after' => 'disabled');
                    }
                }
            }
            if (null !== $publicvisible) {
                if ($data['current_publicvisible'] !== $publicvisible) {
                    if ($publicvisible === '1') {
                        $this->entity->setVisiblePublic();
                        $differ['PublicVisible'] = array('before' => 'disabled', 'after' => 'enabled');
                    } elseif ($publicvisible === '0') {
                        $this->entity->setHidePublic();
                        $differ['PublicVisible'] = array('before' => 'enabled', 'after' => 'disabled');
                    }
                }
            }
            if (null !== $extint) {
                if ($data['current_extint'] !== $extint) {
                    if ($extint === '1') {
                        $this->entity->setAsLocal();
                        $this->entity->createAclResource();
                        $differ['Local/External'] = array('before' => 'external', 'after' => 'local');
                    } elseif ($extint === '0') {
                        $this->entity->setAsExternal();
                        $differ['Local/External'] = array('before' => 'local', 'after' => 'external');
                    }
                }
            }
            if (!empty($validuntildate) && !empty($validuntiltime)) {
                $validuntil = new DateTime($validuntildate . 'T' . $validuntiltime, new \DateTimeZone('UTC'));
                $this->entity->setValidTo($validuntil);
            } else {
                $this->entity->setValidTo(null);
            }
            if (!empty($validfromdate) && !empty($validfromtime)) {
                $validfrom = new DateTime($validfromdate . 'T' . $validfromtime);
                $this->entity->setValidFrom($validfrom);
            } else {
                $this->entity->setValidFrom(null);
            }
            if (count($differ) > 0) {
                $this->tracker->save_track('idp', 'modification', $this->entity->getEntityId(), serialize($differ), false);
            }
            $this->em->persist($this->entity);

            try {
                $this->em->flush();
                $data['success_message'] = lang('rr_entstate_updated');
            } catch (Exception $e) {
                $data['error'] = 'Unknown error occurred during saving changes';
                log_message('error', __METHOD__ . ' ' . $e);
            }
        }
        $data['current_locked'] = $this->entity->getLocked();
        $data['current_active'] = $this->entity->getActive();
        $data['current_extint'] = $this->entity->getLocal();
        $data['current_publicvisible'] = (int)$this->entity->getPublicVisible();
        $data['entityid'] = $this->entity->getEntityId();
        $data['name'] = $this->entity->getName();
        $data['id'] = $this->entity->getId();
        $data['type'] = strtolower($this->entity->getType());
        $validfrom = $this->entity->getValidFrom();
        $validfromdate = '';
        $validfromtime = '';
        if (!empty($validfrom)) {
            $validfromdate = jaggerDisplayDateTimeByOffset($validfrom, 0, 'Y-m-d');
            $validfromtime = jaggerDisplayDateTimeByOffset($validfrom, 0, 'H:i');
        }
        $validuntil = $this->entity->getValidTo();
        $validuntildate = '';
        $validuntiltime = '';
        if (!empty($validuntil)) {
            $validuntildate = jaggerDisplayDateTimeByOffset($validuntil, 0, 'Y-m-d');
            $validuntiltime = jaggerDisplayDateTimeByOffset($validuntil, 0, 'H:i');
        }
        $data['current_validuntildate'] = $validuntildate;
        $data['current_validuntiltime'] = $validuntiltime;
        $data['current_validfromdate'] = $validfromdate;
        $data['current_validfromtime'] = $validfromtime;
        $data['content_view'] = 'manage/entitystate_form_view';
        $this->load->view(MY_Controller::$page, $data);
    }

}
