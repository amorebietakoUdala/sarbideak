<?php

namespace App\Controller;

use Countable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BaseController extends AbstractController
{
    protected array $queryParams = []; 

    protected function loadQueryParameters(Request $request) {
        if (
            $request->getMethod() === Request::METHOD_GET || 
            $request->getMethod() === Request::METHOD_POST || 
            $request->getMethod() === Request::METHOD_DELETE ) {
            $this->queryParams['page'] = 1;
            $this->queryParams['pageSize'] = 10;
            $this->queryParams['sortName'] = 0;
            $this->queryParams['sortOrder'] = 'asc';
            $this->queryParams['returnUrl'] = null;
            $this->queryParams = array_merge($this->queryParams, $request->query->all());
            if ( $this->queryParams !== null ) {
                $query = parse_url($this->queryParams['returnUrl'], PHP_URL_QUERY);
                parse_str($query,$query);
                $this->queryParams = array_merge($this->queryParams, $query);
            }
        }
    }

    protected function getPaginationParameters() : array {
        return $this->queryParams;
    }

    protected function getAjax(): bool {
        if ( array_key_exists('ajax', $this->queryParams) ) {
            return $this->queryParams['ajax'] === 'true' ? true : false;
        }
        
        return false;
    }

    protected function render(string $view, array $parameters = [], Response $response = null): Response {
        $paginationParameters = $this->getPaginationParameters();
        $viewParameters = array_merge($parameters, $paginationParameters);
        return parent::render($view, $viewParameters, $response);
    }

    protected function renderForm(string $view, array $parameters = [], Response $response = null): Response {
        $paginationParameters = $this->getPaginationParameters();
        $viewParameters = array_merge($parameters, $paginationParameters);
        return parent::renderForm($view, $viewParameters, $response);
    }

    protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse {
        $paginationParameters = $this->getPaginationParameters();
        $viewParameters = array_merge($parameters, $paginationParameters);
        unset($viewParameters['returnUrl']);
        return parent::redirectToRoute($route, $viewParameters, $status);
    }    

    protected function removeBlankFilters($filter) {
        $criteria = [];
        foreach ( $filter as $key => $value ) {
            if (null !== $value) {
                $criteria[$key] = $value;
            }
        }
        return $criteria;
    }

    protected function removePaginationParameters(array $filters) {
        unset($filters['page'],$filters['pageSize'],$filters['sortName'],$filters['sortOrder']);
        return $filters;
    }

    /**
     * @param Countable|array $data Param must be Countable or array
     *
     * @return bool Returns true if it has elements
     */
        protected function hasElements($collection) {
        return count($collection) > 0 ? true: false;
    }
}
