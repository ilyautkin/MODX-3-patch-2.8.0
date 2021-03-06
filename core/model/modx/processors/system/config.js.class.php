<?php
/*
 * This file is part of MODX Revolution.
 *
 * Copyright (c) MODX, LLC. All Rights Reserved.
 *
 * For complete copyright and license information, see the COPYRIGHT and LICENSE
 * files found in the top-level directory of this distribution.
 */

/**
 * Outputs the $modx->config to JSON
 *
 * @param string $action If action is set, load the action namespace into MODx.config
 * @param string $wctx If wctx is set, load the context settings of wctx into MODx.config
 *
 * @package modx
 * @subpackage processors.system
 */
class modConfigJsProcessor extends modProcessor
{

    public function process()
    {
        if (!$this->modx->user->isAuthenticated('mgr')) {
            return '';
        }
        $this->modx->getVersionData();

        $wctx = $this->getProperty('wctx', '');
        if (!empty($wctx)) {
            $workingContext = $this->modx->getContext($wctx);
            if ($workingContext instanceof modContext) {
                $workingContext->prepare();
            } else {
                return $this->modx->error->failure($this->modx->error->failure($this->modx->lexicon('permission_denied')));
            }
        } else {
            $workingContext =& $this->modx->context;
        }

        /* calculate custom resource classes */
        $this->modx->lexicon->load('resource');
        $resourceClasses = array();
        $resourceClassesDrop = array();
        $resourceClassNames = $this->modx->getDescendants('modResource');
        $resourceClassNames = array_diff($resourceClassNames, array('modResource'));
        foreach ($resourceClassNames as $resourceClassName) {
            /** @var modResource $obj */
            $obj = $this->modx->newObject($resourceClassName);
            if ($obj->showInContextMenu) {
                $lex = $obj->getContextMenuText();
                $resourceClasses[$resourceClassName] = $lex;
            }

            if ($obj->allowDrop != -1) {
                $resourceClassesDrop[$resourceClassName] = $obj->allowDrop;
            }
        }

        $template_url = $workingContext->getOption('manager_url', MODX_MANAGER_URL, $this->modx->_userConfig) . 'templates/' . $workingContext->getOption('manager_theme', 'default', $this->modx->_userConfig) . '/';
        $c = array(
            'base_url' => $workingContext->getOption('base_url', MODX_BASE_URL, $this->modx->_userConfig),
            'connectors_url' => $workingContext->getOption('connectors_url', MODX_CONNECTORS_URL, $this->modx->_userConfig),
            'icons_url' => $template_url . 'images/ext/modext/',
            'manager_url' => $workingContext->getOption('manager_url', MODX_MANAGER_URL, $this->modx->_userConfig),
            'template_url' => $template_url,
            'http_host' => $workingContext->getOption('http_host', MODX_HTTP_HOST, $this->modx->_userConfig),
            'site_url' => $workingContext->getOption('site_url', MODX_SITE_URL, $this->modx->_userConfig),
            'http_host_remote' => MODX_URL_SCHEME . $workingContext->getOption('http_host', MODX_HTTP_HOST, $this->modx->_userConfig),
            'user' => $this->modx->user->get('id'),
            'version' => $this->modx->version['full_version'],
            'resource_classes' => $resourceClasses,
            'resource_classes_drop' => $resourceClassesDrop,
        );

        // Handle default context
        $ctx = $this->modx->getContext($this->modx->getOption('default_context', null, 'web'));
        if ($ctx instanceof modContext && $ctx->prepare()) {
            $c['default_site_url'] = $ctx->makeUrl($ctx->getOption('site_start'));
        }

        /* if action is set, load the action namespace into MODx.config */
        $action = $this->getProperty('action');
        if ($action != '' && isset($this->modx->actionMap[$action])) {

            /* pre-2.3 actions */
            if (intval($action) > 0) {
                $c['namespace'] = $this->modx->actionMap['namespace'];
                $c['namespace_path'] = $this->modx->actionMap['namespace_path'];
                $c['namespace_assets_path'] = $this->modx->actionMap['namespace_assets_path'];
                $c['help_url'] = ltrim($this->modx->actionMap['help_url'], '/');
            } else {
                $namespace = $this->modx->getOption('namespace', $this->getProperties(), 'core');
                /** @var modNamespace $namespace */
                $namespace = $this->modx->getObject('modNamespace', $namespace);
                if ($namespace) {
                    $c['namespace'] = $namespace->get('name');
                    $c['namespace_path'] = $namespace->get('path');
                    $c['namespace_assets_path'] = $namespace->get('assets_url');
                }
            }
        }

        $c = array_merge($this->modx->config, $workingContext->config, $this->modx->_userConfig, $c);

        unset($c['password'], $c['username'], $c['mail_smtp_pass'], $c['mail_smtp_user'], $c['proxy_password'], $c['proxy_username'], $c['connections'], $c['connection_init'], $c['connection_mutable'], $c['dbname'], $c['database'], $c['table_prefix'], $c['driverOptions'], $c['dsn'], $c['session_name'], $c['assets_path'], $c['base_path'], $c['cache_path'], $c['connectors_path'], $c['core_path'], $c['friendly_alias_translit_class_path'], $c['manager_path'], $c['processors_path']);

        $o = "Ext.namespace('MODx'); MODx.config = ";
        $o .= $this->modx->toJSON($c);
        $o .= '; MODx.perm = {};';

        // Load actions for backwards compatibility (DEPRECATED)
        $actions = $this->modx->request->getAllActionIDs();
        $o .= 'MODx.action = ' . $this->modx->toJSON($actions) . ';';

        if ($this->modx->user) {
            $accessPermissionsQuery = $this->modx->newQuery('modAccessPermission');
            $accessPermissionsQuery->select('name');
            $accessPermissionsQuery->distinct();
            $permissions = $this->modx->getIterator('modAccessPermission', $accessPermissionsQuery);
            $permissionValues = [];
            foreach ($permissions as $permisson) {
                $name = $permisson->get('name');
                $permissionValues[$name] = $this->modx->hasPermission($name);
            }
            $o .= 'MODx.perm = ' . $this->modx->toJSON($permissionValues) . ';';

            $o .= 'MODx.user = {id:"' . $this->modx->user->get('id') . '",username:"' . $this->modx->user->get('username') . '"}';
        }
        @session_write_close();
        header('Content-Type: application/x-javascript');
        return $o;
    }
}

return 'modConfigJsProcessor';
