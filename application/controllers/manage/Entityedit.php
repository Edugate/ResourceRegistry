<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * ResourceRegistry3
 * 
 * @package     RR3
 * @author      Middleware Team HEAnet 
 * @copyright   Copyright (c) 2013, HEAnet Limited (http://www.heanet.ie)
 * @license     MIT http://www.opensource.org/licenses/mit-license.php
 *  
 */

/**
 * Entityedit Class
 * 
 * @package     RR3
 * @author      Janusz Ulanowski <janusz.ulanowski@heanet.ie>
 */
class Entityedit extends MY_Controller
{

    protected $current_site;
    protected $tmp_providers;
    protected $tmp_error;
    protected $type;
    protected $disallowedparts = array();
    protected $entityid;
    protected $idpsscoscope = array();
    protected $aascope = array();

    public function __construct()
    {
        parent::__construct();
        $this->load->library(array('form_element', 'form_validation', 'approval'));
        $this->tmp_providers = new models\Providers;
        $this->load->helper(array('shortcodes', 'form'));
        $this->tmp_error = '';
        $this->type = null;
        $entpartschangesdisallowed = $this->config->item('entpartschangesdisallowed');
        if (!empty($entpartschangesdisallowed) && is_array($entpartschangesdisallowed))
        {
            $this->disallowedparts = $this->config->item('entpartschangesdisallowed');
        }
    }

    private function _submit_validate($id)
    {
        $register = false;
        if (strcmp($id, 'idp') == 0 || strcmp($id, 'sp') == 0)
        {
            $register = true;
            $this->type = strtoupper($id);
        }
        $result = false;
        $y = $this->input->post();

        $staticisdefault = FALSE;
        if (isset($y['f']))
        {
            $loggedin = $this->j_auth->logged_in();
            $this->_save_draft($id, $y['f']);

            $this->form_validation->set_rules('f[usestatic]', 'use metadata', "valid_static[" . base64_encode($this->input->post('f[static]')) . ":::" . $this->input->post('f[entityid]') . " ]");


            // required if not static is set
            if (isset($y['f']['usestatic']) && $y['f']['usestatic'] === 'accept')
            {
                $staticisdefault = TRUE;
            }
            if (!$register)
            {
                if (in_array('entityid', $this->disallowedparts))
                {
                    $this->form_validation->set_rules('f[entityid]', lang('rr_entityid'), 'trim|no_white_spaces|required|min_length[5]|max_length[255]|matches_value[' . $this->entityid . ']');
                }
                else
                {
                    $this->form_validation->set_rules('f[entityid]', lang('rr_entityid'), 'trim|no_white_spaces|required|min_length[5]|max_length[255]|entityid_unique_update[' . $id . ']');
                }
                if (in_array('scope', $this->disallowedparts))
                {
                    $this->form_validation->set_rules('f[scopes][idpsso]', lang('rr_scope') . ' (IDPSSO)', 'trim|xss_clean|valid_scopes|max_length[2500]|str_matches_array[' . serialize($this->idpssoscope) . ']');
                    $this->form_validation->set_rules('f[scopes][aa]', lang('rr_scope') . ' (AA)', 'trim|xss_clean|valid_scopes|max_length[2500]|str_matches_array[' . serialize($this->aascope) . ']');
                }
                else
                {
                    $this->form_validation->set_rules('f[scopes][idpsso]', lang('rr_scope'), 'trim|xss_clean|valid_scopes|max_length[2500]');
                    $this->form_validation->set_rules('f[scopes][aa]', lang('rr_scope'), 'trim|xss_clean|valid_scopes|max_length[2500]');
                }
            }
            else
            {
                $this->form_validation->set_rules('f[entityid]', lang('rr_entityid'), 'trim|no_white_spaces|required|min_length[5]|max_length[255]|entity_unique');
                if (!$loggedin)
                {
                    $this->form_validation->set_rules('f[primarycnt][mail]', lang('rr_youcntmail'), 'trim|required|valid_email|xss_clean');
                }
            }



            $this->form_validation->set_rules('f[regauthority]', lang('rr_regauthority'), 'trim|xss_clean');
            $this->form_validation->set_rules('f[registrationdate]', lang('rr_regdate'), 'trim|xss_clean|valid_date_past');
            $this->form_validation->set_rules('f[privacyurl]', lang('rr_defaultprivacyurl'), 'trim|xss_clean|valid_url');
            $this->form_validation->set_rules('f[validfrom]', lang('rr_validfrom'), 'trim|xss_clean');
            $this->form_validation->set_rules('f[validto]', lang('rr_validto'), 'trim|xss_clean');
            if (array_key_exists('lname', $y['f']))
            {
                foreach ($y['f']['lname'] as $k => $v)
                {
                    $this->form_validation->set_rules('f[lname][' . $k . ']', lang('localizednamein') . ' ' . $k, 'trim|required|xss_clean');
                }
                if (count($y['f']['lname']) == 0)
                {
                    $this->tmp_error = lang('errnoorgnames');
                    return false;
                }
            }
            else
            {
                $this->tmp_error = lang('errnoorgnames');
                return false;
            }
            if (isset($y['f']['uii']['idpsso']['displayname']) && is_array($y['f']['uii']['idpsso']['displayname']))
            {
                foreach ($y['f']['uii']['idpsso']['displayname'] as $k => $v)
                {
                    $this->form_validation->set_rules('f[uii][idpsso][displayname][' . $k . ']', 'UUI ' . sprintf(lang('lrr_displayname'), $k) . '', 'trim|min_length[5]|max_length[255]|xss_clean');
                }
            }
            if (isset($y['f']['uii']['idpsso']['desc']) && is_array($y['f']['uii']['idpsso']['desc']))
            {
                foreach ($y['f']['uii']['idpsso']['desc'] as $k => $v)
                {
                    $this->form_validation->set_rules('f[uii][idpsso][desc][' . $k . ']', 'UUI ' . lang('rr_description') . ' ' . lang('in') . ' ' . $k . '', 'trim|min_length[5]|max_length[500]|xss_clean');
                }
            }
            if (isset($y['f']['uii']['idpsso']['helpdesk']) && is_array($y['f']['uii']['idpsso']['helpdesk']))
            {
                foreach ($y['f']['uii']['idpsso']['helpdesk'] as $k => $v)
                {
                    $this->form_validation->set_rules('f[uii][idpsso][helpdesk][' . $k . ']', 'UUI ' . lang('rr_helpdeskurl') . ' ' . lang('in') . ' ' . $k . '', 'trim|valid_url|min_length[5]|max_length[500]|xss_clean');
                }
            }
            if (array_key_exists('ldisplayname', $y['f']))
            {
                foreach ($y['f']['ldisplayname'] as $k => $v)
                {
                    $this->form_validation->set_rules('f[ldisplayname][' . $k . ']', lang('localizeddisplaynamein') . ' ' . $k, 'trim|required|xss_clean');
                }
            }
            else
            {
                $this->tmp_error = lang('errnoorgdisnames');
                return false;
            }
            if (array_key_exists('lhelpdesk', $y['f']))
            {
                foreach ($y['f']['lhelpdesk'] as $k => $v)
                {
                    $this->form_validation->set_rules('f[lhelpdesk][' . $k . ']', lang('localizedhelpdeskin') . ' ' . $k, 'trim|required|valid_url');
                }
            }
            else
            {
                $this->tmp_error = lang('errnoorgurls');
                return false;
            }


            if (array_key_exists('contact', $y['f']))
            {
                foreach ($y['f']['contact'] as $k => $v)
                {
                    $this->form_validation->set_rules('f[contact][' . $k . '][email]', '' . lang('rr_contactemail') . '', 'trim|valid_email');
                    $this->form_validation->set_rules('f[contact][' . $k . '][type]', '' . lang('rr_contacttype') . '', 'trim|valid_contact_type');
                    $this->form_validation->set_rules('f[contact][' . $k . '][fname]', '' . lang('rr_contactfirstname') . '', 'trim|xss_clean');
                    $this->form_validation->set_rules('f[contact][' . $k . '][sname]', '' . lang('rr_contactlastname') . '', 'trim|xss_clean');
                }
            }
            if (array_key_exists('prot', $y['f']))
            {
                foreach ($y['f']['prot'] as $key => $value)
                {
                    foreach ($value as $k => $v)
                    {
                        $this->form_validation->set_rules('f[prot][' . $key . '][' . $k . ']', 'trim');
                    }
                }
            }
            /**
             * certificates
             */
            if (array_key_exists('crt', $y['f']))
            {
                if (array_key_exists('spsso', $y['f']['crt']))
                {
                    foreach ($y['f']['crt']['spsso'] as $k => $v)
                    {
                        if (is_numeric($k))
                        {
                            $this->form_validation->set_rules('f[crt][spsso][' . $k . '][certdata]', 'cert data', 'trim|xss_clean|verify_cert_nokeysize');
                        }
                        else
                        {
                            $this->form_validation->set_rules('f[crt][spsso][' . $k . '][certdata]', 'cert data', 'trim|xss_clean|verify_cert');
                        }
                        $this->form_validation->set_rules('f[crt][spsso][' . $k . '][usage]', '' . lang('rr_certificateuse') . '', 'trim|required|xss_clean');
                    }
                }
                if (array_key_exists('idpsso', $y['f']['crt']))
                {
                    foreach ($y['f']['crt']['idpsso'] as $k => $v)
                    {
                        if (is_numeric($k))
                        {
                            $this->form_validation->set_rules('f[crt][idpsso][' . $k . '][certdata]', 'Certificate', 'trim|xss_clean|verify_cert_nokeysize');
                        }
                        else
                        {
                            $this->form_validation->set_rules('f[crt][idpsso][' . $k . '][certdata]', 'Certificate', 'trim|xss_clean|verify_cert');
                        }
                        $this->form_validation->set_rules('f[crt][idpsso][' . $k . '][usage]', '' . lang('rr_certificateuse') . '', 'trim|required|xss_clean');
                    }
                }
                if (array_key_exists('aa', $y['f']['crt']))
                {
                    foreach ($y['f']['crt']['aa'] as $k => $v)
                    {
                        if (is_numeric($k))
                        {
                            $this->form_validation->set_rules('f[crt][aa][' . $k . '][certdata]', 'Certificate', 'trim|xss_clean|verify_cert_nokeysize');
                        }
                        else
                        {
                            $this->form_validation->set_rules('f[crt][aa][' . $k . '][certdata]', 'Certificate', 'trim|xss_clean|verify_cert');
                        }
                        $this->form_validation->set_rules('f[crt][aa][' . $k . '][usage]', '' . lang('rr_certificateuse') . '', 'trim|required|xss_clean');
                    }
                }
            }

            /**
             * service locations
             */
            $nosso = 0;
            $nossobindings = array();
            $noidpslo = array();
            if (array_key_exists('srv', $y['f']))
            {
                if (!array_key_exists('SingleSignOnService', $y['f']['srv']))
                {
                    $y['f']['srv']['SingleSignOnService'] = array();
                }
                foreach ($y['f']['srv']['SingleSignOnService'] as $k => $v)
                {
                    $nossobindings[] = $y['f']['srv']['SingleSignOnService'][$k]['bind'];
                    $tmp1 = $this->form_validation->set_rules('f[srv][SingleSignOnService][' . $k . '][url]', 'SingleSignOnService URL for: ' . $y['f']['srv']['SingleSignOnService']['' . $k . '']['bind'], 'trim|max_length[254]|valid_url');
                    $tmp2 = $this->form_validation->set_rules('f[srv][SingleSignOnService][' . $k . '][bind]', 'SingleSignOnService Binding protocol', 'required');
                    if ($tmp1 && $tmp2 && !empty($y['f']['srv']['SingleSignOnService']['' . $k . '']['url']))
                    {
                        ++$nosso;
                    }
                }
                if (array_key_exists('IDPSingleLogoutService', $y['f']['srv']))
                {
                    foreach ($y['f']['srv']['IDPSingleLogoutService'] as $k => $v)
                    {
                        $noidpslo[] = $y['f']['srv']['IDPSingleLogoutService']['' . $k . '']['bind'];
                        $this->form_validation->set_rules('f[srv][IDPSingleLogoutService][' . $k . '][url]', 'IDP SingleLogoutService URL for: ' . $y['f']['srv']['IDPSingleLogoutService']['' . $k . '']['bind'], 'trim|max_length[254]|valid_url');
                        $this->form_validation->set_rules('f[srv][IDPSingleLogoutService][' . $k . '][bind]', 'IDP SingleLogoutService Binding protocol', 'required');
                    }
                }
                if (array_key_exists('SPSingleLogoutService', $y['f']['srv']))
                {
                    foreach ($y['f']['srv']['SPSingleLogoutService'] as $k => $v)
                    {
                        $nospslo[] = $y['f']['srv']['SPSingleLogoutService']['' . $k . '']['bind'];
                        $this->form_validation->set_rules('f[srv][SPSingleLogoutService][' . $k . '][url]', 'SP SingleLogoutService URL for: ' . $y['f']['srv']['SPSingleLogoutService']['' . $k . '']['bind'], 'trim|max_length[254]|valid_url');
                        $this->form_validation->set_rules('f[srv][SPSingleLogoutService][' . $k . '][bind]', 'SP SingleLogoutService Binding protocol', 'required');
                    }
                }
                if (!array_key_exists('AssertionConsumerService', $y['f']['srv']) && ($this->type === 'SP' or $this->type === 'BOTH'))
                {
                    $y['f']['srv']['AssertionConsumerService'] = array();
                }
                if (array_key_exists('AssertionConsumerService', $y['f']['srv']))
                {
                    $acsindexes = array();
                    $acsurls = array();
                    $acsdefault = array();
                    foreach ($y['f']['srv']['AssertionConsumerService'] as $k => $v)
                    {
                        $this->form_validation->set_rules('f[srv][AssertionConsumerService][' . $k . '][url]', 'AssertionConsumerService URL', 'trim|max_length[254]|valid_url');
                        $this->form_validation->set_rules('f[srv][AssertionConsumerService][' . $k . '][bind]', 'AssertionConsumerService Binding protocol', 'trim|xss_clean');
                        $this->form_validation->set_rules('f[srv][AssertionConsumerService][' . $k . '][order]', 'AssertionConsumerService Index', 'trim|xss_clean');

                        $tmpurl = trim($y['f']['srv']['AssertionConsumerService']['' . $k . '']['url']);
                        $tmporder = trim($y['f']['srv']['AssertionConsumerService']['' . $k . '']['order']);
                        $tmpdefaultexist = array_key_exists('default', $y['f']['srv']['AssertionConsumerService']['' . $k . '']);
                        if (!empty($tmpurl))
                        {
                            if (!empty($v['order']))
                            {
                                $acsindexes[] = $v['order'];
                            }
                            $acsurls[] = 1;
                            if (!empty($tmporder) && !is_numeric($tmporder))
                            {
                                $this->tmp_error = 'One of the index order in ACS is not numeric';
                                return false;
                            }
                            if (array_key_exists('default', $y['f']['srv']['AssertionConsumerService']['' . $k . '']))
                            {
                                $acsdefault[] = 1;
                            }
                        }
                    }
                    if ($this->type != 'IDP')
                    {
                        if (count($acsindexes) != count(array_unique($acsindexes)))
                        {

                            $this->tmp_error = 'Not unique indexes found for ACS';
                            return false;
                        }
                        if (count($acsurls) < 1 && empty($staticisdefault))
                        {

                            $this->tmp_error = lang('rr_acsurlatleastone');
                            return false;
                        }
                        if (count($acsdefault) > 1)
                        {

                            $this->tmp_error = lang('rr_acsurlonlyonedefault');
                            return false;
                        }
                    }
                }

                if (array_key_exists('SPArtifactResolutionService', $y['f']['srv']))
                {
                    $spartindexes = array();
                    $sparturls = array();
                    foreach ($y['f']['srv']['SPArtifactResolutionService'] as $k => $v)
                    {
                        $this->form_validation->set_rules('f[srv][SPArtifactResolutionService][' . $k . '][url]', 'SP ' . lang('ArtifactResolutionService') . ' URL', 'trim|max_length[254]|valid_url');
                        $this->form_validation->set_rules('f[srv][SPArtifactResolutionService][' . $k . '][bind]', 'SP ' . lang('ArtifactResolutionService') . ' Binding protocol', 'trim|xss_clean');

                        $tmpurl = trim($y['f']['srv']['SPArtifactResolutionService']['' . $k . '']['url']);
                        $tmporder = trim($y['f']['srv']['SPArtifactResolutionService']['' . $k . '']['order']);
                        if (!empty($tmpurl))
                        {
                            if (!empty($v['order']))
                            {
                                $spartindexes[] = $v['order'];
                            }
                            $sparturls[] = 1;
                            if (!empty($tmporder) && !is_numeric($tmporder))
                            {
                                $this->tmp_error = 'One of the index order in SP ArtifactResolutionService is not numeric';
                                return false;
                            }
                        }
                    }
                    if ($this->type != 'IDP')
                    {
                        if (count($spartindexes) != count(array_unique($spartindexes)))
                        {

                            $this->tmp_error = 'Not unique indexes found for SP ArtifactResolutionService';
                            return false;
                        }
                    }
                }
                if (array_key_exists('IDPArtifactResolutionService', $y['f']['srv']))
                {
                    $idpartindexes = array();
                    $idparturls = array();
                    foreach ($y['f']['srv']['IDPArtifactResolutionService'] as $k => $v)
                    {
                        $this->form_validation->set_rules('f[srv][IDPArtifactResolutionService][' . $k . '][url]', 'IDP ArtifactResolutionService URL', 'trim|max_length[254]|valid_url');
                        $this->form_validation->set_rules('f[srv][IDPArtifactResolutionService][' . $k . '][bind]', 'IDP ArtifactResolutionService Binding protocol', 'trim|xss_clean');

                        $tmpurl = trim($y['f']['srv']['IDPArtifactResolutionService']['' . $k . '']['url']);
                        $tmporder = trim($y['f']['srv']['IDPArtifactResolutionService']['' . $k . '']['order']);
                        if (!empty($tmpurl))
                        {
                            if (!empty($v['order']))
                            {
                                $idpartindexes[] = $v['order'];
                            }
                            $idparturls[] = 1;
                            if (!empty($tmporder) && !is_numeric($tmporder))
                            {
                                $this->tmp_error = 'One of the index order in IDP ArtifactResolutionService is not numeric';
                                return false;
                            }
                        }
                    }
                    if ($this->type != 'SP')
                    {
                        if (count($idpartindexes) != count(array_unique($idpartindexes)))
                        {

                            $this->tmp_error = 'Not unique indexes found for IDP ArtifactResolutionService';
                            return false;
                        }
                    }
                }


                if (array_key_exists('DiscoveryResponse', $y['f']['srv']))
                {
                    $drindexes = array();
                    $drurls = array();

                    foreach ($y['f']['srv']['DiscoveryResponse'] as $k => $v)
                    {
                        $this->form_validation->set_rules('f[srv][DiscoveryResponse][' . $k . '][url]', 'DiscoveryResponse URL', 'trim|required|max_length[254]|valid_url');
                        $this->form_validation->set_rules('f[srv][DiscoveryResponse][' . $k . '][bind]', 'DiscoveryResponse Binding protocol', 'trim|required|xss_clean');
                        $this->form_validation->set_rules('f[srv][DiscoveryResponse][' . $k . '][order]', 'DiscoveryResponse Index', 'trim|required|numeric|xss_clean');

                        $tmpurl = trim($y['f']['srv']['DiscoveryResponse']['' . $k . '']['url']);
                        $tmporder = trim($y['f']['srv']['DiscoveryResponse']['' . $k . '']['order']);

                        if (!empty($tmpurl))
                        {
                            if (!empty($v['order']))
                            {
                                $drindexes[] = $v['order'];
                            }
                            $drurls[] = 1;
                            if (!empty($tmporder) && !is_numeric($tmporder))
                            {
                                $this->tmp_error = 'One of the index order in DiscoveryResponse is not numeric';
                                return false;
                            }
                        }
                    }
                    if ($this->type != 'IDP')
                    {
                        if (count($drindexes) != count(array_unique($drindexes)))
                        {
                            $this->tmp_error = 'Not unique indexes found for DiscoveryResponse';
                            return false;
                        }
                    }
                }
                if (array_key_exists('RequestInitiator', $y['f']['srv']))
                {
                    foreach ($y['f']['srv']['RequestInitiator'] as $k => $v)
                    {
                        $this->form_validation->set_rules('f[srv][RequestInitiator][' . $k . '][url]', 'RequestInitiator URL', 'trim|max_length[254]|valid_url');
                    }
                }
            }
            $result = $this->form_validation->run();
            if ($this->type != 'SP')
            {

                if (empty($nosso) && !$staticisdefault)
                {
                    $this->tmp_error = 'At least one SSO must be set';
                    return false;
                }
                if (!empty($nossobindings) && is_array($nossobindings) && count($nossobindings) > 0 && count(array_unique($nossobindings)) < count($nossobindings))
                {
                    $this->tmp_error = 'duplicate binding protocols for SSO found in sent form';
                    return false;
                }
                if (!empty($noidpslo) && is_array($noidpslo) && count($noidpslo) > 0 && count(array_unique($noidpslo)) < count($noidpslo))
                {
                    $this->tmp_error = 'duplicate binding protocols for IDP SLO found in sent form';
                    return false;
                }
                if (!empty($nosplo) && is_array($nosplo) && count($nosplo) > 0 && count(array_unique($nospslo)) < count($nospslo))
                {
                    $this->tmp_error = 'duplicate binding protocols for SP SLO found in sent form';
                    return false;
                }
            }
        }
        return $result;
    }

    private function _save_draft($id, $data)
    {
        if (isset($data['lname']))
        {
            $data['lname'] = array_filter($data['lname']);
        }
        else
        {
            $data['lname'] = array();
        }
        if (isset($data['ldisplayname']))
        {
            $data['ldisplayname'] = array_filter($data['ldisplayname']);
        }
        else
        {
            $data['ldisplayname'] = array();
        }
        if (isset($data['lhelpdesk']))
        {
            $data['lhelpdesk'] = array_filter($data['lhelpdesk']);
        }
        else
        {
            $data['lhelpdesk'] = array();
        }
        if (isset($data['crt']['idpsso']))
        {
            $data['crt']['idpsso'] = array_filter($data['crt']['idpsso']);
        }
        else
        {
            $data['crt']['idpsso'] = array();
        }
        if (isset($data['crt']['aa']))
        {
            $data['crt']['aa'] = array_filter($data['crt']['aa']);
        }
        else
        {
            $data['crt']['aa'] = array();
        }
        if (isset($data['crt']['spsso']))
        {
            $data['crt']['spsso'] = array_filter($data['crt']['spsso']);
        }
        else
        {
            $data['crt']['spsso'] = array();
        }

        if (isset($data['uii']['idpsso']['desc']))
        {
            $data['uii']['idpsso']['desc'] = array_filter($data['uii']['idpsso']['desc']);
        }
        else
        {
            $data['uii']['idpsso']['desc'] = array();
        }
        if (isset($data['uii']['idpsso']['helpdesk']))
        {
            $data['uii']['idpsso']['helpdesk'] = array_filter($data['uii']['idpsso']['helpdesk']);
        }
        else
        {
            $data['uii']['idpsso']['helpdesk'] = array();
        }
        if (isset($data['uii']['idpsso']['displayname']))
        {
            $data['uii']['idpsso']['displayname'] = array_filter($data['uii']['idpsso']['displayname']);
        }
        else
        {
            $data['uii']['idpsso']['displayname'] = array();
        }
        if (isset($data['prvurl']['idpsso']))
        {
            $data['prvurl']['idpsso'] = array_filter($data['prvurl']['idpsso']);
        }
        else
        {
            $data['prvurl']['idpsso'] = array();
        }
        if (isset($data['regpol']))
        {
            $data['regpol'] = array_filter($data['regpol']);
        }
        else
        {
            $data['regpol'] = array();
        }

        if (isset($data['coc']))
        {
            $data['coc'] = array_filter($data['coc']);
        }
        else
        {
            $data['coc'] = array();
        }
        if (isset($data['prvurl']['spsso']))
        {
            $data['prvurl']['spsso'] = array_filter($data['prvurl']['spsso']);
        }
        else
        {
            $data['prvurl']['spsso'] = array();
        }
        if (isset($data['srv']['AssertionConsumerService']))
        {
            $data['srv']['AssertionConsumerService'] = array_filter($data['srv']['AssertionConsumerService']);
        }
        else
        {
            $data['srv']['AssertionConsumerService'] = array();
        }


        if (isset($data['srv']['RequestInitiator']))
        {
            $data['srv']['RequestInitiator'] = array_filter($data['srv']['RequestInitiator']);
        }
        else
        {
            $data['srv']['RequestInitiator'] = array();
        }

        if (isset($data['srv']['SPArtifactResolutionService']))
        {
            $data['srv']['SPArtifactResolutionService'] = array_filter($data['srv']['SPArtifactResolutionService']);
        }
        else
        {
            $data['srv']['SPArtifactResolutionService'] = array();
        }
        if (isset($data['srv']['IDPArtifactResolutionService']))
        {
            $data['srv']['IDPArtifactResolutionService'] = array_filter($data['srv']['IDPArtifactResolutionService']);
        }
        else
        {
            $data['srv']['IDPArtifactResolutionService'] = array();
        }
        if (isset($data['srv']['IDPAttributeService']))
        {
            $data['srv']['IDPAttributeService'] = array_filter($data['srv']['IDPAttributeService']);
        }
        else
        {
            $data['srv']['IDPAttributeService'] = array();
        }
        if (isset($data['srv']['DiscoveryResponse']))
        {
            $data['srv']['DiscoveryResponse'] = array_filter($data['srv']['DiscoveryResponse']);
        }
        else
        {
            $data['srv']['DiscoveryResponse'] = array();
        }
        if (isset($data['srv']['SingleSignOnService']))
        {

            $data['srv']['SingleSignOnService'] = array_filter($data['srv']['SingleSignOnService']);
        }
        else
        {
            $data['srv']['SingleSignOnService'] = array();
        }
        if (isset($data['srv']['IDPSingleLogoutService']))
        {

            $data['srv']['IDPSingleLogoutService'] = array_filter($data['srv']['IDPSingleLogoutService']);
        }
        else
        {
            $data['srv']['IDPSingleLogoutService'] = array();
        }
        if (isset($data['prot']['spsso']))
        {
            $data['prot']['spsso'] = array_filter($data['prot']['spsso']);
        }
        else
        {
            $data['prot']['spsso'] = array();
        }
        if (isset($data['contact']))
        {
            foreach ($data['contact'] as $k => $v)
            {
                if (empty($v['email']))
                {
                    unset($data['contact']['' . $k . '']);
                }
            }
        }
        else
        {
            $data['contact'] = array();
        }
        $n = 'entform' . $id;
        $this->session->set_userdata($n, $data);
    }

    private function _get_draft($id)
    {
        $n = 'entform' . $id;
        return $this->session->userdata($n);
    }

    private function _discard_draft($id)
    {
        $n = 'entform' . $id;
        $this->session->unset_userdata($n);
    }

    private function _check_perms($id)
    {
        $has_write_access = $this->zacl->check_acl($id, 'write', 'entity');

        if (!$has_write_access)
        {
            show_error('No access to edit', 403);
            return false;
        }
    }

    public function show($id)
    {
        $loggedin = $this->j_auth->logged_in();
        if (!$loggedin)
        {
            $this->session->set_flashdata('target', $this->current_site);
            redirect('auth/login', 'location');
        }
        else
        {
            $this->load->library('zacl');
        }

        $ent = $this->tmp_providers->getOneById($id);
        if (empty($ent))
        {
            show_error('Provider not found', '404');
        }
        $locked = $ent->getLocked();
        $is_local = $ent->getLocal();
        if (!$is_local)
        {
            show_error('Access Denied. Identity/Service Provider is not localy managed.', 403);
        }
        if ($locked)
        {
            show_error('Access Denied. Identity/Service Provider is locked and cannod be modified.', 403);
        }
        $this->entityid = $ent->getEntityId();
        $this->idpssoscope = $ent->getScope('idpsso');
        $this->aascope = $ent->getScope('aa');
        $this->type = $ent->getType();
        $this->_check_perms($id);


        if ($this->input->post('discard'))
        {
            $this->_discard_draft($id);
            redirect(base_url() . 'manage/entityedit/show/' . $id, 'location');
        }
        elseif ($this->_submit_validate($id) === TRUE)
        {
            $y = $this->input->post('f');
            $submittype = $this->input->post('modify');
            $this->_save_draft($id, $y);
            if ($submittype === 'modify')
            {
                $this->load->library('providerupdater');
                $c = $this->_get_draft($id);
                if (!empty($c) && is_array($c))
                {

                    $updateresult = $this->providerupdater->updateProvider($ent, $c);
                    if ($updateresult)
                    {
                        $cacheId = 'mcircle_' . $ent->getId();
                        $this->em->persist($ent);
                        $this->em->flush();
                        $this->_discard_draft($id);
                        $keyPrefix = getCachePrefix();
                        $this->load->driver('cache', array('adapter' => 'memcached', 'key_prefix' => $keyPrefix));
                        $this->cache->delete($cacheId);
                        $showsuccess = TRUE;
                    }
                }
            }
        }
        $entsession = $this->_get_draft($id);
        if (!empty($entsession))
        {
            $data['sessform'] = true;
        }

        $data['y'] = $entsession;
        $lang = MY_Controller::getLang();

        $titlename = $ent->getNameToWebInLang($lang, $ent->getType());
        $this->title = $titlename . ' :: ' . lang('title_provideredit');

        /**
         * @todo check locked
         */
        $data['entdetail'] = array('displayname' => $titlename, 'name' => $ent->getName(), 'id' => $ent->getId(), 'entityid' => $ent->getEntityId(), 'type' => $ent->getType());

        if (!empty($showsuccess))
        {
            $data['success_message'] = lang('updated');
            $data['content_view'] = 'manage/entityedit_success_view';
            $this->load->view('page', $data);
            return;
        }
        /**
         * menutabs array('id'=>xx,'v')
         */
        $data['error_messages'] = validation_errors('<p>', '</p>');
        $data['error_messages2'] = $this->tmp_error;
        $this->session->set_flashdata('entformerror', '');

        $menutabs[] = array('id' => 'organization', 'value' => '' . lang('taborganization') . '', 'form' => $this->form_element->NgenerateEntityGeneral($ent, $entsession));
        $menutabs[] = array('id' => 'contacts', 'value' => '' . lang('tabcnts') . '', 'form' => $this->form_element->NgenerateContactsForm($ent, $entsession));
        $menutabs[] = array('id' => 'uii', 'value' => '' . lang('tabuii') . '', 'form' => $this->form_element->NgenerateUiiForm($ent, $entsession));
        $menutabs[] = array('id' => 'tabsaml', 'value' => '' . lang('tabsaml') . '', 'form' => $this->form_element->NgenerateSAMLTab($ent, $entsession));
        $menutabs[] = array('id' => 'certificates', 'value' => '' . lang('tabcerts') . '', 'form' => $this->form_element->NgenerateCertificatesForm($ent, $entsession));
        $menutabs[] = array('id' => 'entcategories', 'value' => '' . lang('tabentcategories') . '', 'form' => $this->form_element->NgenerateEntityCategoriesForm($ent, $entsession));
        $menutabs[] = array('id' => 'staticmetadata', 'value' => '' . lang('tabstaticmeta') . '', 'form' => $this->form_element->NgenerateStaticMetadataForm($ent, $entsession));
        $menutabs[] = array('id' => 'other', 'value' => '' . lang('tabotherforms') . '', 'form' => $this->form_element->NgenerateOtherFormLinks($ent));

        $data['menutabs'] = $menutabs;
        $data['titlepage'] = '<a href="' . base_url() . 'providers/detail/show/' . $data['entdetail']['id'] . '">' . $data['entdetail']['displayname'] . '</a>';
        $data['content_view'] = 'manage/entityedit_view.php';
        $this->load->view('page', $data);
    }

    private function _isfromsimplereg()
    {
        //$mbody = $this->input->post('metadatabody');
        $fromSimpleMode = $this->input->post('advanced');
        if (!empty($fromSimpleMode) && strcmp($fromSimpleMode, 'advanced') == 0)
        {
            return true;
        }

        return false;
    }

    public function register($t = null)
    {
        $data['registerForm'] = TRUE;
        $t = trim($t);
        if (empty($t) || !(strcmp($t, 'idp') == 0 || strcmp($t, 'sp') == 0))
        {
            show_error('Not found', 404);
            return;
        }
        $ent = new models\Provider;
        $ent->setLocal(TRUE);
        if (strcmp($t, 'idp') == 0)
        {
            $ent->setType('IDP');
            $data['titlepage'] = lang('rr_idp_register_title');
        }
        else
        {
            $ent->setType('SP');
            $data['titlepage'] = lang('rr_sp_register_title');
        }
        $loggedin = $this->j_auth->logged_in();
        if (!$loggedin)
        {
            $data['anonymous'] = TRUE;
        }
        else
        {
            $data['anonymous'] = FALSE;
            $currentusername = $this->j_auth->current_user();
            $u = $this->em->getRepository("models\User")->findOneBy(array('username' => '' . $currentusername . ''));

            $data['loggeduser'] = array(
                'username' => '' . $currentusername . '',
                'fullname' => '' . $u->getFullname() . '',
                'email' => '' . $u->getEmail() . '',
            );
        }

        $fedCollection = $this->em->getRepository("models\Federation")->findBy(array('is_public' => TRUE, 'is_active' => TRUE));
        if (count($fedCollection) > 0)
        {
            $data['federations'] = array();
            /**
             *  generate dropdown list of public federations
             */
            $data['federations']['none'] = lang('noneatthemoment');
            foreach ($fedCollection as $key)
            {
                $data['federations'][$key->getName()] = $key->getName();
            }
        }


        /**
         * check if submit from simpleform
         */
        if ($this->_isfromsimplereg())
        {
            $metadatabody = trim($this->input->post('metadatabody'));
            if (!empty($metadatabody))
            {
                $this->load->library('xmlvalidator');
                libxml_use_internal_errors(true);
                $metadataDOM = new \DOMDocument();
                $metadataDOM->strictErrorChecking = FALSE;
                $metadataDOM->WarningChecking = FALSE;
                $metadataDOM->loadXML($metadatabody);
                $isValid = $this->xmlvalidator->validateMetadata($metadataDOM, FALSE, FALSE);
                if (!$isValid)
                {
                    log_message('warning', __METHOD__ . ' invalid metadata had been pasted in registration form');
                    $this->tmp_error = lang('err_pastedtxtnotvalidmeta');
                }
                else
                {
                    $this->_discard_draft($t);
                    $this->load->library('metadata2array');
                    $xpath = new DomXPath($metadataDOM);
                    $namespaces = h_metadataNamespaces();
                    foreach ($namespaces as $key => $value)
                    {
                        $xpath->registerNamespace($key, $value);
                    }
                    $domlist = $metadataDOM->getElementsByTagName('EntityDescriptor');
                    if (count($domlist) == 1)
                    {
                        $d = array();
                        foreach ($domlist as $l)
                        {
                            $entarray = $this->metadata2array->entityDOMToArray($l, TRUE);
                        }
                        $o = current($entarray);
                        if (isset($o['type']) && strcasecmp($o['type'], $t) == 0)
                        {
                            $ent->setProviderFromArray(current($entarray));
                        }
                        else
                        {
                            $this->tmp_error = lang('regcantimporttype');
                        }
                    }
                }
            }
        }
        elseif ($this->input->post('discard'))
        {
            $this->_discard_draft($t);
            redirect(base_url() . 'providers/' . strtolower($t) . '_registration', 'location');
        }
        elseif ($this->_submit_validate($t) === TRUE)
        {

            log_message('debug', __METHOD__ . ' line ' . __LINE__ . ' GKS  _submit_validate');
            $y = $this->input->post('f');
            log_message('debug', __METHOD__ . ' line ' . __LINE__ . ' GKS ' . serialize($y));
            $submittype = $this->input->post('modify');
            if ($submittype === 'modify')
            {
                \log_message('debug', __METHOD__ . 'GKS submittype=modify');
                $this->load->library('providerupdater');
                $c = $this->_get_draft($t);
                if (!empty($c) && is_array($c))
                {

                    \log_message('debug', __METHOD__ . ' GKS data from draft: '.  serialize($c));
                    $ent = $this->providerupdater->updateProvider($ent, $c);

                    if ($ent)
                    {
                        $registrationAutority = $this->config->item('registrationAutority');
                        if (!empty($registrationAutority))
                        {
                            $ent->setRegistrationAuthority(trim($registrationAutority));
                        }
                        $ent->setActive(TRUE);
                        /// create queue
                        $q = new models\Queue;
                        if (!empty($u))
                        {
                            $contactMail = $u->getEmail();
                            $q->setCreator($u);
                        }
                        $q->setAction("Create");
                        $lnames = $ent->getMergedLocalName();
                        if (is_array($lnames) and count($lnames) > 0)
                        {
                            $q->setName(current($lnames));
                        }
                        else
                        {
                            $q->setName('unknown');
                        }
                        $ttype = $ent->getType();
                        $mm = $ent->getCoc();




                        if (!empty($y['federation']))
                        {
                            try
                            {
                                $federation = $this->em->getRepository("models\Federation")->findOneBy(array('name' => '' . $y['federation'] . ''));
                            }
                            catch (Exception $e)
                            {
                                log_message('error', __METHOD__ . ' ' . $e);
                                show_error('Internal Server Error', 500);
                                return;
                            }
                        }
                        if (!empty($federation))
                        {
                            $ispublic = $federation->getPublic();
                            $isactive = $federation->getActive();
                            if ($ispublic && $isactive)
                            {
                                $membership = new models\FederationMembers;
                                $membership->setJoinState('1');
                                $membership->setProvider($ent);
                                $membership->setFederation($federation);
                                $ent->getMembership()->add($membership);
                            }
                            else
                            {
                                log_message('warning', 'Federation is not public, cannot register sp with join fed with name ' . $federation->getName());
                            }
                        }








                        log_message('debug','GKS before convert: entitid: '.$ent->getEntityId());
                        $convertedToArray = $ent->convertToArray(True);
                       
                        log_message('debug','GKS convertedToArray: '.serialize($convertedToArray));

                        if (strcmp($ttype, 'IDP') == 0)
                        {
                            $q->addIDP($convertedToArray);
                        }
                        else
                        {
                            $q->addSP($convertedToArray);
                        }
                        if (empty($contactMail))
                        {
                            $contactMail = $this->input->post('f[primarycnt][mail]');
                        }
                        $q->setEmail($contactMail);

                        $q->setToken();
                        $sourceIP = $this->input->ip_address();
                        $messageTemplateParams = array(
                            'requestermail' => $contactMail,
                            'token' => $q->getToken(),
                            'requestersourceip' => $sourceIP,
                            'orgname' => $ent->getName(),
                            'serviceentityid' => $ent->getEntityId(),
                        );

                        $messageTemplate = $this->email_sender->providerRegRequest($ttype, $messageTemplateParams, NULL);
                        if (!empty($messageTemplate))
                        {
                            $this->email_sender->addToMailQueue(array('greqisterreq', 'gidpregisterreq'), null, $messageTemplate['subject'], $messageTemplate['body'], array(), FALSE);
                        }


                        $this->em->persist($q);
                        $this->em->detach($ent);
                        try
                        {
                            $this->em->flush();
                            $redirect_to = current_url();
                            redirect(base_url() . 'manage/entityedit/registersuccess');
                        }
                        catch (Exception $e)
                        {
                            log_message('error', __METHOD__ . ' ' . $e);
                            show_error('Internal Server Error', 500);
                            return;
                        }
                    }
                }
            }
        }
        ////////////////////////////////
        $entsession = $this->_get_draft($t);
        if (!empty($entsession))
        {
            $data['sessform'] = true;
        }
        $data['titlepage'] .= '  - ' . lang('subtl_advancedmode') . '';
        $data['error_messages'] = validation_errors('<div>', '</div>');
        $data['error_messages2'] = $this->tmp_error;
        $this->session->set_flashdata('entformerror', '');
        $menutabs[] = array('id' => 'organization', 'value' => '' . lang('taborganization') . '', 'form' => $this->form_element->NgenerateEntityGeneral($ent, $entsession));
        $menutabs[] = array('id' => 'contacts', 'value' => '' . lang('tabcnts') . '', 'form' => $this->form_element->NgenerateContactsForm($ent, $entsession));
        $menutabs[] = array('id' => 'uii', 'value' => '' . lang('tabuii') . '', 'form' => $this->form_element->NgenerateUiiForm($ent, $entsession));
        $menutabs[] = array('id' => 'tabsaml', 'value' => '' . lang('tabsaml') . '', 'form' => $this->form_element->NgenerateSAMLTab($ent, $entsession));
        $menutabs[] = array('id' => 'certificates', 'value' => '' . lang('tabcerts') . '', 'form' => $this->form_element->NgenerateCertificatesForm($ent, $entsession));
        //$menutabs[] = array('id' => 'entcategories', 'value' => ''.lang('tabentcategories').'', 'form' => $this->form_element->NgenerateEntityCategoriesForm($ent, $entsession));
        //$menutabs[] = array('id' => 'staticmetadata', 'value' => ''.lang('tabstaticmeta').'', 'form' => $this->form_element->NgenerateStaticMetadataForm($ent, $entsession));
        //$menutabs[] = array('id' => 'other', 'value' => ''.lang('tabotherforms').'', 'form' => $this->form_element->NgenerateOtherFormLinks($ent));

        $data['menutabs'] = $menutabs;
        $data['content_view'] = 'manage/entityedit_view.php';
        $this->load->view('page', $data);
    }

    function registersuccess()
    {

        $data['content_view'] = 'register_success';
        $this->load->view('page', $data);
    }

}
