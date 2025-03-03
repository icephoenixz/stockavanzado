<?php
/**
 * This file is part of StockAvanzado plugin for FacturaScripts
 * Copyright (C) 2020-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\StockAvanzado\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Model\Base\ModelCore;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\StockAvanzado\Model\ConteoStock;
use FacturaScripts\Plugins\StockAvanzado\Model\LineaConteoStock;

/**
 * Description of EditConteoStock
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditConteoStock extends EditController
{
    public function getModelClassName(): string
    {
        return 'ConteoStock';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'warehouse';
        $data['title'] = 'stock-count';
        $data['icon'] = 'fas fa-scroll';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function addLineAction(): bool
    {
        // permisos
        if (false === $this->permissions->allowUpdate) {
            $this->toolBox()->i18nLog()->warning('not-allowed-update');
            return true;
        }

        // obtenemos datos del formulario
        $code = $this->request->get('code');
        $barcode = $this->request->request->get('codbarras');
        $ref = $this->request->request->get('referencia');
        if (empty($code) || (empty($barcode) && empty($ref))) {
            return true;
        }

        // cargamos el conteo
        $conteo = new ConteoStock();
        if (false === $conteo->loadFromCode($code)) {
            return true;
        }

        // buscamos la referencia
        $variante = new Variante();
        $where = empty($barcode) ?
            [new DataBaseWhere('referencia', $ref)] :
            [new DataBaseWhere('codbarras', $barcode)];
        if (false === $variante->loadFromCode('', $where)) {
            $this->toolBox()->i18nLog()->warning('no-data');
            return true;
        }

        // comprobamos si ya existe la línea
        $newLine = new LineaConteoStock();
        $where2 = [
            new DataBaseWhere('idconteo', $conteo->idconteo),
            new DataBaseWhere('referencia', $variante->referencia)
        ];
        if (false === $newLine->loadFromCode('', $where2)) {
            $newLine->cantidad = 0.0;
            $newLine->idconteo = $conteo->idconteo;
            $newLine->idproducto = $variante->idproducto;
            $newLine->referencia = $variante->referencia;
        }

        // guardamos la línea
        $newLine->cantidad++;
        $newLine->fecha = date(ModelCore::DATETIME_STYLE);
        $newLine->nick = $this->user->nick;
        if (false === $newLine->save()) {
            $this->toolBox()->i18nLog()->error('record-save-error');
            return true;
        }

        $this->toolBox()->i18nLog()->notice('record-updated-correctly');
        return true;
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');
        $this->createViewsLines();
    }

    protected function createViewsLines(string $viewName = 'ListLineaConteoStock')
    {
        $this->addListView($viewName, 'LineaConteoStock', 'lines', 'fas fa-list');
        $this->views[$viewName]->template = 'EditConteoStockLines.html.twig';
    }

    protected function deleteLineAction(): bool
    {
        if (false === $this->permissions->allowDelete) {
            $this->toolBox()->i18nLog()->warning('not-allowed-delete');
            return true;
        }

        $lineaConteo = new LineaConteoStock();
        $idlinea = $this->request->request->get('idlinea');
        if ($lineaConteo->loadFromCode($idlinea) && $lineaConteo->delete()) {
            $this->toolBox()->i18nLog()->notice('record-deleted-correctly');
            return true;
        }

        $this->toolBox()->i18nLog()->error('record-deleted-error');
        return true;
    }

    protected function editLineAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            $this->toolBox()->i18nLog()->warning('not-allowed-update');
            return true;
        }

        $lineaConteo = new LineaConteoStock();
        $idlinea = $this->request->request->get('idlinea');
        if (false === $lineaConteo->loadFromCode($idlinea)) {
            $this->toolBox()->i18nLog()->notice('record-not-found');
            return true;
        }

        $lineaConteo->cantidad = (float)$this->request->request->get('quantity');
        $lineaConteo->fecha = date(ModelCore::DATETIME_STYLE);
        $lineaConteo->nick = $this->user->nick;
        if (false === $lineaConteo->save()) {
            $this->toolBox()->i18nLog()->error('record-save-error');
            return true;
        }

        $this->toolBox()->i18nLog()->notice('record-updated-correctly');
        return true;
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'add-line':
                return $this->addLineAction();

            case 'delete-line':
                return $this->deleteLineAction();

            case 'edit-line':
                return $this->editLineAction();

            case 'update-stock':
                return $this->updateStockAction();

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mvn = $this->getMainViewName();

        switch ($viewName) {
            case 'ListLineaConteoStock':
                $where = [new DataBaseWhere('idconteo', $this->getViewModelValue($mvn, 'idconteo'))];
                $view->loadData('', $where, ['referencia' => 'ASC']);
                break;

            case $mvn:
                parent::loadData($viewName, $view);
                if (empty($view->model->nick)) {
                    $view->model->nick = $this->user->nick;
                }
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    protected function updateStockAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            $this->toolBox()->i18nLog()->warning('not-allowed-update');
            return true;
        }

        $model = $this->getModel();
        if (false === $model->loadFromCode($this->request->get('code'))) {
            $this->toolBox()->i18nLog()->warning('record-not-found');
            return true;
        }

        if (false === $model->updateStock()) {
            $this->toolBox()->i18nLog()->error('record-save-error');
            return true;
        }

        $this->toolBox()->i18nLog()->notice('record-updated-correctly');
        $this->toolBox()->i18nLog('audit')->info('applied-stock-count', ['%code%' => $model->primaryColumnValue()]);
        return true;
    }
}
