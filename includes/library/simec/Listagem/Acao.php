<?php
/**
 * Implementa a classe base de criação de ações.
 *
 * @version $Id: Acao.php 141842 2018-07-18 13:08:08Z victormachado $
 * @filesource
 */

/**
 * Implementação base de todas as ações da listagem.
 *
 * Faz o processamento de condições e parâmetros de configuração da ação, além de fazer o parse das
 * ações para o padrão bootstrap e, ao ser transformada em string, retorna a string HTML da ação (dentro de uma TD).
 *
 * Todas as classes de implementação de ações estão dentro do pacote Simec\View\Listagem\Acao.
 *
 * @abstract
 * @author  Maykel S. Braz <maykelbraz@mec.gov.br>
 * @package Simec\View\Listagem
 */
abstract class Simec_Listagem_Acao
{
    /**
     * Indica que o parâmetro adicionado é um parametro extra e faz parte da linha de dados do banco.
     */
    const PARAM_EXTRA = 1;
    /**
     * Indica que o parâmetro adicionado é um parâmetro externo e seu valor é inserido diretamente na callback.
     */
    const PARAM_EXTERNO = 2;

    /**
     * @var string Pós fixo do ícone no bootstrap.
     * @link http://getbootstrap.com/components/#glyphicons
     */
    protected $icone;

    /**
     * @var string Título exibido ao posicionar o mouse sobre o ícone da ação.
     */
    protected $titulo;

    /**
     * @var string Cor do ícone da ação. Utilize as "cores" padrões do bootstrap: default, danger, warning, info, success, primary.
     */
    protected $cor = 'default';

    /**
     * @var string Nome da função executada pela ação. Na maioria dos casos será uma função JS.
     */
    protected $callbackJS;

    /**
     * @var mixed[] Conjunto de dados da linha associada à ação.
     */
    protected $dados;

    /**
     * @var mixed[] Condições de exibição da ação. Se estiver vazia, a ação é sempre exibida.
     */
    protected $condicoes = [];

    /**
     * @var Simec_Regra_Agregador Regra de apresentação da ação
     */
    protected $regras;

    /**
     * @var string[] Lista com o nome dos campos extras que serão enviados à chamada de self::$callbackJs.
     */
    protected $parametrosExtra = [];

    /**
     * @var mixed[] Lista com valores externos que serão enviados à chamada de self::$callbackJS.
     */
    protected $parametrosExternos = [];

    /**
     * @var string[] Lista com as partes do ID da ação, utilizado quando a linha tem um ID composto.
     */
    protected $partesID = [];

    /**
     * @var mixed[] Lista de configuração adicionais da ação - estas configurações são tratadas diretamente na classe da ação.
     */
    protected $config = [];

    /**
     * Ao criar uma nova ação, ela deve ter um ícone atribuído a ela.
     *
     * @throws Exception
     */
    public function __construct()
    {
        if (!isset($this->icone)) {
            throw new Exception('Esta ação não tem um ícone definido para ela.');
        }
    }

    /**
     * Atribui um título para a ação.
     *
     * @param string $titulo Título da ação.
     *
     * @return \Simec_Listagem_Acao
     */
    public function setTitulo($titulo)
    {
        if (!empty($titulo)) {
            $this->titulo = $titulo;
        }

        return $this;
    }

    /**
     * Atribui uma cor para o botão da ação.
     *
     * @param string $cor Cor da ação.
     *
     * @return \Simec_Listagem_Acao
     */
    public function setCor($cor)
    {
        if (!empty($cor)) {
            $this->cor = $cor;
        }

        return $this;
    }

    /**
     * Atribui uma função de callback para a ação.
     *
     * @param string $callback O nome da função de callback.
     *
     * @return \Simec_Listagem_Acao
     * @throws Exception
     */
    public function setCallback($callback)
    {
        if (empty($callback)) {
            throw new Exception('A callback da ação não pode ser vazia.');
        }
        $this->callbackJS = $callback;

        return $this;
    }

    /**
     * Atribui à ação a lista de dados da linha à qual ela está associada.
     *
     * @param mixed[] $dados Lista de dados da linha da ação.
     *
     * @return \Simec_Listagem_Acao
     */
    public function setDados(array $dados)
    {
        $this->dados = $dados;

        return $this;
    }

    /**
     * Atribui à ação uma lista de condições de exibição.
     *
     * @param mixed[] $condicao Condições de exibição da ação.
     *
     * @return \Simec_Listagem_Acao
     */
    public function addCondicao(array $condicao)
    {
        $this->condicoes[] = $condicao;

        return $this;
    }

    /**
     * Atribui à ação uma regra para ser validada
     *
     * @param mixed[] $regras
     * @return $this
     */
    public function addRegra($regras = []){
        $this->regras = $regras;
        return $this;
    }

    /**
     * Adiciona parametros extras à ação.
     *
     * Tanto outros campos da lista de dados, quanto valores externos podem ser adicionados à ação.
     *
     * @param int     $tipo   Indica se é uma parâmetro extra, ou um parâmetro externo.
     * @param mixed[] $params Uma lista com os demais parâmetros.
     *
     * @return \Simec_Listagem_Acao
     *
     * @uses self::PARAM_EXTRA
     * @uses self::PARAM_EXTERNO
     */
    public function addParams($tipo, $params)
    {
        switch ($tipo) {
            case self::PARAM_EXTRA:
                $tpParam = 'parametrosExtra';
                break;
            case self::PARAM_EXTERNO:
                $tpParam = 'parametrosExternos';
                break;
        }

        if (is_array($params)) {
            foreach ($params as $key => $param) {
                $this->{$tpParam}[$key] = $param;
            }
        } else {
            $this->{$tpParam}[$key] = $param;
        }

        return $this;
    }

    /**
     *
     *
     * @param type $partesID
     *
     * @return \Simec_Listagem_Acao
     * @throws Exception
     */
    public function setPartesID($partesID)
    {
        if (!is_array($partesID) && !empty($partesID)) {
            $partesID = [$partesID];
        }

        foreach ($partesID as $parteID) {
            if (!key_exists($parteID, $this->dados)) {
                throw new Exception("A parte do ID '{$parteID}' não existe no conjunto de dados da listagem.");
            }

            $this->partesID[] = $parteID;
        }

        return $this;
    }

    /**
     * Armazena as configurações adicionais de cada ação.
     *
     * @param array $config Lista de configurações especiais da ação.
     *
     * @return \Simec_Listagem_Acao
     */
    public function setConfig(array $config)
    {
        if (empty($config)) {
            return;
        }

        $this->config = $config;

        return $this;
    }

    /**
     * Retorna o HTML da ação.
     *
     * @return string
     */
    public function render()
    {
        if (empty($this->dados)) {
            trigger_error('Não há dados associados a esta ação.', E_USER_ERROR);
        }

        if (empty($this->callbackJS)) {
            trigger_error('Não há uma callback JS associada a esta ação.', E_USER_ERROR);
        }

        // -- Ação não atende condição de exibição
        if (!$this->exibirAcao()) {
            return '-';
        }

        return $this->renderAcao();
    }

    public function __toString()
    {
        return $this->render();
    }

    /**
     * @todo Criar um método get icon para abstrair o span e poder formatar para todas as ações.
     */
    protected function renderAcao()
    {
        $acao = <<<HTML
<a href="javascript:%s(%s);" title="%s">%s</a>
HTML;

        return sprintf(
            $acao,
            $this->callbackJS,
            $this->getCallbackParams(),
            $this->titulo,
            $this->renderGlyph()
        );
    }

    /**
     * Cria a lista de parametros que será utilizada na chamada da callback.
     * Existem 3 categorias de parâmetros:<br />
     * <ul><li><b>id</b>: O primeiro parâmetro é o id da linha. O valor do id da linha é o valor<br />
     * do primeiro campo da linha de dados;</li>
     * <li><b>parâmetros extra</b>: Ao definir a ação, o usuário pode indicar um conjunto de valores adicionais<br />
     * que devem ser passados para a callback. Eles vem logo depois do id.</li>
     * <li><b>parâmetros externos</b>: Também na definição da função, o usuário pode indicar um conjunto de valores<br />
     * externos (não inclusos no conjunto de dados da linha) que são inclusos na lista de parâmetros log depois dos<br />
     * parâmetros extra.</li></ul>
     *
     * @param bool $paramsComoArray Indica que os parâmetros da callback devem ser retornados como um array.
     *
     * @return string
     * @throws Exception Se algum parâmetro que não existe nos dados da linha for informado na lista de parâmetros extras, é gerada uma exceção.
     * @todo Implementar passagem do nome do parametro como chave do array
     */
    protected function getCallbackParams($paramsComoArray = false)
    {
        $params = [];
        $params[] = "'" . current($this->dados) . "'"; // -- Informando o primeiro parâmetro sempre como string

        // -- parametros extras
        foreach ($this->parametrosExtra as $param) {
            if (!key_exists($param, $this->dados)) {
                trigger_error("O parâmetro '{$param}' não existe no conjunto de dados da listagem.", E_USER_ERROR);
            }
            $params[$param] = "'{$this->dados[$param]}'";
        }
        // -- parametros extras
        foreach ($this->parametrosExternos as $key => $param) {
            $params[$key] = "'{$param}'";
        }

        // -- Transformar a lista de parâmetros em um array
        if ($paramsComoArray) {
            $params = '[' . implode(', ', $params) . ']';
            if (!is_null($parametroinicial)) {
                $params = "'{$parametroinicial}', {$params}";
            }
        } else {
            if (!is_null($parametroinicial)) {
                array_unshift($params, "'{$parametroinicial}'");
            }
            $params = implode(', ', $params);
        }

        return $params;
    }

    protected function getAcaoID()
    {
        if (empty($this->partesID)) {
            reset($this->dados);

            return current($this->dados);
        }

        $partes = array_intersect_key(
            $this->dados,
            array_combine(
                $this->partesID,
                array_fill(0, count($this->partesID), null)
            )
        );

        return implode('_', $partes);
    }

    protected function exibirAcao()
    {
        if (empty($this->condicoes) && empty($this->regras)) {
            return true;
        }

        if (is_array($this->condicoes) && !empty($this->condicoes)) {
            // -- Se houver uma entrada para a coluna no array de condicionais, as condições devem ser avaliadas
            foreach ($this->condicoes as &$condicao) {
                if (empty($condicao['op'])) {
                    $condicao['op'] = 'igual';
                }
                $method = 'checa' . ucfirst($condicao['op']);
                // -- Se a chave 'valor' do array $condicao for um campo retornado pela listagem a condição utilizará o valor
                // -- do campo como valor da condição.
                if (!is_array($condicao['valor']) && array_key_exists($condicao['valor'], $this->dados)) {
                    $valor = $this->dados[$condicao['valor']];
                } else {
                    $valor = $condicao['valor'];
                }
                // -- Se ao menos uma das condições falhar, a ação não deve ser exibida
                if (!$this->$method($this->dados[$condicao['campo']], $valor)) {
                    return false;
                }
            }
        }

        if (is_array($this->regras) && !empty($this->regras)) {
            if (is_array($this->regras['campo'])) {
                foreach ($this->regras['campo'] as $campo) {
                    $this->regras['opcoes'][$campo] = $this->dados[$campo];
                }
            } else {
                $this->regras['opcoes'][$this->regras['campo']] = $this->dados[$this->regras['campo']];
            }
            if (!Simec_Regra_Motor::check($this->regras['regra'], $this->regras['opcoes'])) {
                return false;
            }
        }

        return true;
    }

    protected function checaIgual($val1, $val2)
    {
        return $val1 == $val2;
    }

    protected function checaDiferente($val1, $val2)
    {
        return $val1 != $val2;
    }

    protected function checaContido($val1, $val2)
    {
        return in_array($val1, $val2);
    }

    protected function checaMaior($val1, $val2)
    {
        return $val1 > $val2;
    }

    protected function checaMenor($val1, $val2)
    {
        return $val1 < $val2;
    }

    protected function renderGlyph()
    {
        $html = <<<HTML
<span class="btn btn-%s btn-sm glyphicon glyphicon-%s"></span>
HTML;

        return sprintf($html, $this->cor, $this->icone);
    }
}
