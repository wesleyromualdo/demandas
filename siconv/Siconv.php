<?php

/**
 * Inclui as classes do mapeamento do webservice.
 */
require_once(APPRAIZ . 'siconv/map/SiconvMap.php');

class Ws_Siconv extends Simec_BasicWS {

    private $user = '05958805681';
    private $pass = 'S!m3c000';

    /*private $user = '44711727604';
    private $pass = 'Vivo@2016';*/

    protected $cachewsdl;
    protected $soapversion;
    protected $dbSchema;

    const CACHE_NONE = 0;
    const CACHE_DISK = 1;
    const CACHE_MEMORY = 2;
    const CACHE_BOTH = 3;

    const SOAP_1_1 = 1;
    const SOAP_1_2 = 2;

    protected $login;

    public function __construct($dbSchema = 'spo', $cachewsdl = self::CACHE_NONE, $soapversion = self::SOAP_1_2, $env = self::DEVELOPMENT)
    {
        if (is_null($env)) {
            $env = (('simec_desenvolvimento' == $_SESSION['baselogin']) || ('simec_espelho_producao' == $_SESSION['baselogin'])) ? self::DEVELOPMENT:self::PRODUCTION;
        }

        $this->cachewsdl = $cachewsdl;
        $this->soapversion = $soapversion;

        $this->setHeaderUsername($this->user);
        $this->setHeaderPassword(md5($this->pass));

        $this->setLogin();

        if (empty($dbSchema)) {
            $this->setLoggerOff();
        } else {
            $this->logRequests = true;
            $this->dbSchema = $dbSchema;
        }

        parent::__construct($env);

        $this->getSoapClient()->setInputHeaders(
            $this->getWsSecurityHeader()
        );

    }

    public function setLogin(){
        $this->login = new LoginWS();
        $this->login->usuario = $this->getHeaderUsername();
        $this->login->senha = $this->getHeaderPassword();
    }

    public function getLogin(){
        return [
            'usuario' => $this->getHeaderUsername(),
            'senha' => $this->getHeaderPassword()
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function loadConnectionOptions()
    {
        $options = new Simec_SoapClient_Options();
        $options
            ->add('exceptions', true)
            ->add('trace', true)
            ->add('encoding', 'ISO-8859-1')
            ->add('cache_wsdl', $this->cachewsdl)
            ->add('soap_version', $this->soapversion);

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadClassMap()
    {
        $classMap = new Simec_SoapClient_ClassMap();
        $classMapClass = new ReflectionClass(get_class($this) . "Map");
        foreach ($classMapClass->getStaticPropertyValue('classmap') as $tipo => $classe) {
            $classMap->add($tipo, $classe);
        }
        return $classMap;
    }

    /**
     * Esta função valida o ambiente em que a classe será utilizada, alterando o link de acesso ao wsdl.
     *
     * @return $this
     */
    protected function loadURL(){
        switch ($this->enviroment){
            case self::PRODUCTION:
                $this->urlWSDL = "https://ws.convenios.gov.br/siconv-siconv-interfaceSiconv-1.0/InterfaceSiconvHandlerBeanImpl?wsdl";
                break;
            case self::STAGING:
            case self::DEVELOPMENT:
                $this->urlWSDL = "https://wshom.convenios.gov.br/siconv-siconv-interfaceSiconv-1.0/InterfaceSiconvHandlerBeanImpl?wsdl";
                break;
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function _init()
    {
        $this->loadURL();
    }

    public function getWSDLTypes()
    {
        return parent::getWSDLTypes(); // TODO: Change the autogenerated stub
    }

    protected function getWsSecurityHeader(){
        $username = trim($this->getHeaderUsername());
        $nonce = mt_rand();
        $created = gmdate('Y-m-d\TH:i:s\Z');
        $password = base64_encode(
            pack('H*',
                sha1(
                    pack('H*', $nonce) .
                    pack('a*', $created) .
                    pack('a*', $this->getHeaderPassword()))
            )
        );
        $nonce = base64_encode(pack('H*', $nonce));

        $headerXML = <<<XML
<wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
	<wsse:UsernameToken wsu:Id="UsernameToken-4">
		<wsse:Username>{$username}</wsse:Username>
		<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest">{$password}</wsse:Password>
		<wsse:Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">{$nonce}</wsse:Nonce>
		<wsu:Created>{$created}</wsu:Created>
	</wsse:UsernameToken>
</wsse:Security>        
XML;

        $authValues = new SoapVar($headerXML, XSD_ANYXML);
        $header = new SoapHeader(
            "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd",
            "Security",
            $authValues,
            true
        );

        return $header;
    }

    /**
     * {@inheritdoc}
     */
    protected function connect()
    {
        global $db;

        parent::connect(); // TODO: Change the autogenerated stub

        if ($this->logRequests) {
            $this->getSoapClient()->startLogger(
                'db',
                array(
                    'tableName' => "{$this->dbSchema}.logws",
                    'fieldMap' => array(
                        'requestContent' => 'lwsrequestcontent',
                        'requestHeader' => 'lwsrequestheader',
                        'requestTimestamp' => 'lwsrequesttimestamp',
                        'responseContent' => 'lwsresponsecontent',
                        'responseHeader' => 'lwsresponseheader',
                        'responseTimestamp' => 'lwsresponsetimestamp',
                        'url' => 'lwsurl',
                        'method' => 'lwsmetodo',
                        'ehErro' => 'lwserro',
                    ),
                    'dbConnection' => $db
                )
            );
        }

    }

    /**
     * Esta função recebe um objeto e retorna um array com seus atributos e as informações do login de acesso ao SICONV.
     *
     * @param $objeto
     * @param bool $addLogin - booleano que determina se as informações do login serão inseridas no array final
     * @return array
     */
    private function trataAtributos($objeto, $addLogin = true){
        $r = new ReflectionObject($objeto);
        $a = [];
        foreach ($r->getDefaultProperties() as $key => $value) {
            $a[$key] = is_object($objeto->$key) ? $this->trataAtributos($objeto->$key, false) : $objeto->$key;
        }
        if($addLogin) {
            $a['login'] = $this->getLogin();
        }
        return $a;
    }

    /**
     * Função que consulta as propostas alteradas do órgão no período
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * // -- INSTANCIA A CLASSE COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $dados = new PropostasAlteradasExportacaoWS();
     * $dados->codigoOrgaoConcedente = '26000';
     * $dados->dataInicialPeriodo => '03/05/2017';
     * $dados->tipoPeriodo = 'dia';
     *
     * $propostas = $siconv->consultaPropostasAlteradasOrgaoPeriodo($dados);
     * </code>
     *
     * @param PropostasAlteradasExportacaoWS $dados
     * @return mixed
     */
    public function consultaPropostasAlteradasOrgaoPeriodo(PropostasAlteradasExportacaoWS $dados)
    {
        $consultaPropostasAlteradasOrgaoPeriodo = new consultaPropostasAlteradasOrgaoPeriodo();
        $consultaPropostasAlteradasOrgaoPeriodo->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('consultaPropostasAlteradasOrgaoPeriodo', [$consultaPropostasAlteradasOrgaoPeriodo], [
        'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    /**
     * Função que retorna as propostas por órgão.
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * // -- INSTANCIA A CLASSE COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $dados = new PropostaExportacaoWS();
     * $dados->codigoOrgaoConcedente = '26000';
     * $dados->ano = '2016';
     *
     * $propostas = $siconv->consultaPropostasOrgao($dados);
     * </code>
     *
     * @param PropostaExportacaoWS $dados
     * @return mixed
     */
    public function consultaPropostasOrgao(PropostaExportacaoWS $dados)
    {
        $consultaPropostaOrgao = new consultaPropostasOrgao();
        $consultaPropostaOrgao->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('consultaPropostasOrgao', array($consultaPropostaOrgao), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    /**
     * Função que retorna os convênios por órgão e ano.
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * // -- INSTANCIA A CLASSE COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $dados = new ParametrosConsultarConveniosOrgaoPorAnoWS();
     * $dados->codigoOrgaoConcedente = '26298';
     * $dados->ano = '2016';
     *
     * $propostas = $siconv->consultarConveniosOrgaoPorAno($dados);
     * </code>
     *
     * @param ParametrosConsultarConveniosOrgaoPorAnoWS $dados
     * @return mixed
     */
    public function consultarConveniosOrgaoPorAno(ParametrosConsultarConveniosOrgaoPorAnoWS $dados)
    {
        $consultarConveniosOrgaoPorAno = new consultarConveniosOrgaoPorAno();
        $consultarConveniosOrgaoPorAno->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('consultarConveniosOrgaoPorAno', array($consultarConveniosOrgaoPorAno), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    /**
     * Função que retorna as informações do programa.
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * // -- INSTANCIA A CLASSE COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $dados = new ParametrosConsultarProgramaWS();
     * $dados->codigoPrograma = 2629820160001;
     *
     * $propostas = $siconv->consultarPrograma($dados);
     * </code>
     *
     * @param ParametrosConsultarProgramaWS $dados
     * @return mixed
     */
    public function consultarPrograma(ParametrosConsultarProgramaWS $dados)
    {
        $consultaPrograma = new consultarPrograma();
        $consultaPrograma->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('consultarPrograma', array($consultaPrograma), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    /**
     * Função que retorna as informações da proposta por ação orçamentária.
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * // -- INSTANCIA A CLASSE COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $dados = new ParametrosConsultarPropostaPorAcaoOrcamentariaWS();
     * $dados->codigoOrgao = '26298';
     * $dados->numeroAcaoOrcamentaria = '20800048';
     *
     * $propostas = $siconv->consultarPropostaPorAcaoOrcamentaria($dados);
     * </code>
     *
     * @param ParametrosConsultarPropostaPorAcaoOrcamentariaWS $dados
     * @return mixed
     */
    public function consultarPropostaPorAcaoOrcamentaria(ParametrosConsultarPropostaPorAcaoOrcamentariaWS $dados)
    {
        $con = new consultarPropostaPorAcaoOrcamentaria();
        $con->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('consultarPropostaPorAcaoOrcamentaria', array($con), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    /**
     * Função que retorna as propostas por CNPJ.
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * // -- INSTANCIA A CLASSE COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $dados = new ParametrosConsultarPropostaPorCNPJWS();
     * $dados->cnpj = '000000000000';
     * $dados->ano = '2016';
     *
     * $propostas = $siconv->consultarPropostaPorCNPJ($dados);
     * </code>
     *
     * @param ParametrosConsultarPropostaPorCNPJWS $dados
     * @return mixed
     */
    public function consultarPropostaPorCNPJ(ParametrosConsultarPropostaPorCNPJWS $dados)
    {
        $con = new consultarPropostaPorCNPJ();
        $con->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('consultarPropostaPorCNPJ', array($con), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    /**
     * Função que retorna as propostas a partir da emenda parlamentar.
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * // -- INSTANCIA A CLASSE COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $dados = new ParametrosConsultarPropostaPorEmendaParlamentarWS();
     * $dados->codigoOrgao = '26000';
     * $dados->numeroEmendaParlamentar = 00000;
     *
     * $propostas = $siconv->consultarPropostaPorEmendaParlamentar($dados);
     * </code>
     *
     * @param ParametrosConsultarPropostaPorEmendaParlamentarWS $dados
     * @return mixed
     */
    public function consultarPropostaPorEmendaParlamentar(ParametrosConsultarPropostaPorEmendaParlamentarWS $dados)
    {
        $con = new consultarPropostaPorEmendaParlamentar();
        $con->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('consultarPropostaPorEmendaParlamentar', array($con), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    /**
     * Função que retorna as propostas a partir do município.
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * // -- INSTANCIA A CLASSE COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $dados = new ParametrosConsultarPropostaPorMunicipioWS();
     * $dados->codigoMunicipio = '000000';
     * $dados->codigoOrgao = '26000';
     *
     * $propostas = $siconv->consultarPropostaPorMunicipio($dados);
     * </code>
     *
     * @param ParametrosConsultarPropostaPorMunicipioWS $dados
     * @return mixed
     */
    public function consultarPropostaPorMunicipio(ParametrosConsultarPropostaPorMunicipioWS $dados)
    {
        $con = new consultarPropostaPorMunicipio();
        $con->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('consultarPropostaPorMunicipio', array($con), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    /**
     * Função que retorna as propostas a partir do programa.
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * // -- INSTANCIA A CLASSE COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $dados = new ParametrosConsultarPropostaPorProgramaWS();
     * $dados->codigoOrgao = '26000';
     * $dados->numeroPrograma = '0000000';
     *
     * $propostas = $siconv->consultarPropostaPorPrograma($dados);
     * </code>
     *
     * @param ParametrosConsultarPropostaPorProgramaWS $dados
     * @return mixed
     */
    public function consultarPropostaPorPrograma(ParametrosConsultarPropostaPorProgramaWS $dados)
    {
        $con = new consultarPropostaPorPrograma();
        $con->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('consultarPropostaPorPrograma', array($con), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    /**
     * Função que retorna as propostas por UF.
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * // -- INSTANCIA A CLASSE COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $dados = new ParametrosConsultarPropostaPorUFWS();
     * $dados->codigoOrgao = '26000';
     * $dados->codigoUF = 'DF';
     *
     * $propostas = $siconv->consultarPropostaPorUF($dados);
     * </code>
     *
     * @param ParametrosConsultarPropostaPorUFWS $dados
     * @return mixed
     */
    public function consultarPropostaPorUF(ParametrosConsultarPropostaPorUFWS $dados)
    {
        $con = new consultarPropostaPorUF();
        $con->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('consultarPropostaPorUF', array($con), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    /**
     * Função de envio do proponente ao SICONV.
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * // -- INSTANCIA AS CLASSES COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $ae = new AlteracaoEstatuariaWS();
     * $ae->data = '04/05/2017 10:22:23';
     * $ae->texto = 'Texto da alteração';
     *
     * $aaep = new AreaAtuacaoEntidadePrivadaWS()
     * $aaep->codigo = '0000'
     *      ->descricao = 'Descrição da área de atuação';
     *
     * $ocws = new RespostaCertidaoWS();
     * $ocws->dataValidade = '02/03/2005 14:03:20'
     *      ->nomeCertidao = 'Nome'
     *      ->possuiCertidao = true;
     *
     * $cws = new CertidaoWS();
     * $cws->fgtsDataEmissao = '02/03/2005 14:03:20'
     *     ->fgtsDataValidade = '02/03/2015 14:03:20'
     *     ->fgtsHora = '00000'
     *     ->fgtsIsento = true
     *     ->fgtsNumero = 'xxxxxxxxxxxx'
     *     ->fgtsTipo = 'xxxx'
     *     ->inssDataEmissao = '02/03/2005 14:03:20'
     *     ->inssDataValidade = '02/03/2005 14:03:20'
     *     ->inssHora = 'xxxxx'
     *     ->inssIsento = true
     *     ->inssNumero = 'xxxxxxxxxxxxxx'
     *     ->inssTipo = 'xxxx'
     *     ->outrasCertidoesWS = $ocws
     *     ->receitaEstadualDataEmissao = ''
     *     ->receitaEstadualDataValidade = ''
     *     ->receitaEstadualHora = ''
     *     ->receitaEstadualIsento = true
     *     ->receitaEstadualNumero = 'xxxxxxxxxxxxxx'
     *     ->receitaEstadualTipo = 'xxxxxx'
     *     ->receitaMunicipalDataEmissao = ''
     *     ->receitaMunicipalDataValidade = ''
     *     ->receitaMunicipalHora = 'xxxxx'
     *     ->receitaMunicipalIsento = true
     *     ->receitaMunicipalNumero = 'xxxxxxxxxxxxxxxxxxx'
     *     ->receitaMunicipalTipo = 'xxxxxx'
     *     ->srfPgfnDataEmissao = ''
     *     ->srfPgfnDataValidade = ''
     *     ->srfPgfnHora = 'xxxxx'
     *     ->srfPgfnIsento = true
     *     ->srfPgfnNumero = 'xxxxxxx'
     *     ->srfPgfnTipo = 'xxxxxx';
     *
     * $cnaews = new CnaeWS()
     * $cnaews->codigo = 'xxxx'
     *        ->descricao = 'xxxxxxxxxxxx';
     *
     * $dws = new DirigenteWS();
     * $dws->cargo = 'xxxxx'
     *     ->cpf = 'xxxxxxxxxxx'
     *     ->nome = 'xxxxxxxxxxxxxxxxxx'
     *     ->orgaoExpedidor = 'xxx'
     *     ->profissao = 'xxxxxxxxxxxx'
     *     ->rg = 'xxxxxxxx';
     *
     * $dnd = new DeclaracaoNaoDividaWS()
     * $dnd->dataAssinatura = ''
     *     ->dirigenteSignatarioWS = $dws;
     *
     * $eaws = new EsferaAdministrativaWS();
     * $eaws->codigo = 'xxxxx';
     *
     * $ufws = new UnidadeFederativaWS();
     * $ufws->sigla = 'DF';
     *
     * $munws = new MunicipioWS()
     * $munws->codigo = 'xxxxx'
     *       ->nome = 'xxxxx'
     *       ->unidadeFederativaWS = $ufws;
     *
     * $estws = new EstatutoWS();
     * $estws->cartorio = 'xxxxxx'
     *       ->dataRegistro = ''
     *       ->livroFolha = 'xxxxx'
     *       ->municipioWS = $munws
     *       ->numeroDoRegistroMatricula = 'xxxxxxx'
     *       ->transcricaoEstatuto = 'xxxxxx';
     *
     * $respws = new MembroParticipanteWS()
     * $respws->ativoNoSistema = true
     *        ->cargoFuncao = 'xxxxxx'
     *        ->cep = 'xxxxxxxx'
     *        ->cpf = 'xxxxxxxxxxx'
     *        ->email = 'xxxxxxxxx'
     *        ->endereco = 'xxxxxx'
     *        ->matricula = 'xxxxxxxxx'
     *        ->municipioMembroWS = $munws
     *        ->municipioWS = $munws
     *        ->nome = 'xxxxxxx'
     *        ->orgaoExpedidor = 'xxx'
     *        ->rg = '00000000'
     *        ->senha = 'xxxxxxxx'
     *        ->telefone = '00000000000';
     *
     * $tpip = new TipoIdentificadorParticipeWS();
     * $tpip->codigo = 'xxxxx';
     *
     * $dados = new ParticipantesPropostaWS();
     * $dados->agencia = '0000000'
     *       ->alteracaoEstatuaria = $ae
     *       ->aprovado = true
     *       ->areaAtuacaoEntidadePrivada = $aaep
     *       ->bairroDistrito = 'bairro'
     *       ->cep = '00000000'
     *       ->certidaoAprovada = true
     *       ->certidaoWS = $cws
     *       ->cienteDirigenteNaoRemunerado = false
     *       ->cnaePrimario = 'xxxxxxxxxx'
     *       ->cnaePrimarioWS = $cnaews
     *       ->codigoBanco = 'xxxx'
     *       ->codigoErroSiafi = 'xxxx'
     *       ->consorcioPublico = true
     *       ->contaCorrente = 'xxxxxxxx'
     *       ->cpfUsuarioAprovou = 'xxxxxxxxxxx'
     *       ->declaracaoAprovado = true
     *       ->declaracaoNaoDividaWS = $dnd
     *       ->email = 'xxxxxxxxxxxx'
     *       ->endereco = 'xxxxxxxxxxx'
     *       ->entidadesVinculadas = 'xxxxxxxxxxxxxx'
     *       ->erroSiafi = 'xxxxxxxxxxxxx'
     *       ->esferaAdministrativaWS = $eaws
     *       ->estatutoAprovado = true
     *       ->estatutoWS = $estws
     *       ->executor = true
     *       ->identificacao = 'xxxxx'
     *       ->inscricaoEstadual = 'xxxx'
     *       ->inscricaoMunicipal = 'xxxxxx'
     *       ->interveniente = true
     *       ->mandatario = true
     *       ->municWS = $munws
     *       ->naturezaJuridica = 'xxxxx'
     *       ->nome = 'xxxxxxxx'
     *       ->nomeFantasia = 'xxxxxxxx'
     *       ->proponente = true
     *       ->quadroDirigenteAprovado = true
     *       ->representanteWS = $respws
     *       ->respExercicioWS = $respws
     *       ->responsavelWS = $respws
     *       ->respostaEnvioSiafi = 'xxxxxx'
     *       ->statusEnvioSiafi = 'xxxx'
     *       ->telefone = 'xxxxxxx'
     *       ->telexFaxCaixaPostal = 'xxxxx'
     *       ->tipoIdentificacaoWS = $tpip
     *       ->transcricaoEstatutoONG = 'xxxxxxxxx';
     *
     * $propostas = $siconv->enviaProponente($dados);
     * </code>
     *
     * @param ParticipantesPropostaWS $dados
     * @return mixed
     */
    public function enviaProponente(ParticipantesPropostaWS $dados)
    {
        $con = new enviaProponente();
        $con->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('enviaProponente', array($con), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    /**
     * Função que envia a proposta para o SICONV.
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * $ufws = new UnidadeFederativaWS()
     * $ufws->sigla = 'DF';
     *
     * $munws = new MunicipioWS();
     * $munws->codigo = 'xxxxxx'
     *       ->nome = 'xxxxxxx'
     *       ->unidadeFederativaWS = $ufws;
     *
     * $na = new NaturezaAquisicaoWS();
     * $na->codigo = 'xxxxxx'
     *    ->descricao = 'xxxxx';
     *
     * $nd = new NaturezaDespesaWS();
     * $nd->codigo = 'xxxxx'
     *    ->descricao = 'xxxxxxxxxxx';
     *
     * $ndsi = new NaturezaDespesaSubItemWS();
     * $ndsi->descricaoSubItem = 'xxxxxxx'
     *      ->naturezaDespesaWS = $nd
     *      -> observacao = 'xxxxxxxxx'
     *      ->subItem = 'xxxxxx';
     *
     * $obj = new ObjetoWS();
     * $obj->ehObjetoPadronizado = true
     *     ->justificativa = 'xxxxxxx'
     *     ->nome = 'xxxxxxx';
     *
     * $cbe = new CnpjBeneficiarioEspecificoWS();
     * $cbe->cnpj = '0000000000000000'
     *     ->valorRepasseProposta = 00.0000
     *
     * $cpe = new CnpjProgramaEmendaWS();
     * $cpe->cnpj = '0000000000000000'
     *     ->numeroEmendaParlamentar = '00000'
     *     ->valorRepasseProposta = 00.0000;
     *
     * $orpp = new OrigemRecursoPropProgramaWS()
     * $orpp->cnpjBeneficiarioEspecificoWS = $cbe
     *      ->cnpjProgramaEmendaWS = $cpe
     *      ->qualificacaoProponenteWS = 'xxxxxxx'
     *      ->valorRepasse = 00.0000;
     *
     * $cnpjBeneficiario = new CnpjBeneficiarioEspecificoWS();
     * $cnpjBeneficiario->cnpj = '0000000000000'
     *                  ->valorRepasseProposta = 00.0000;
     *
     * $cnpjProgEmenda = new CnpjProgramaEmendaWS();
     * $cnpjProgEmenda->cnpj = '0000000000000000'
     *                ->numeroEmendaParlamentar = '00000000'
     *                ->valorRepasseProposta = 00.0000;
     *
     * $membro = new MembroParticipanteWS();
     * $membro->ativoNoSistema = true
     *        ->cargoFuncao = 'xxxx'
     *        ->cep = '00000000'
     *        ->cpf = '00000000000'
     *        ->email = 'xxxxxxxxxxxx'
     *        ->endereco = 'xxxxxxx'
     *        ->matricula = 'xxxxx'
     *        ->municipioMembroWS = $munws
     *        ->municipioWS = $munws
     *        ->nome = 'xxxxxxxx'
     *        ->orgaoExpedidor = 'xxxxx'
     *        ->rg = '00000000'
     *        ->senha = 'xxxxx'
     *        ->telefone = 'xxxxxx';
     *
     * $ae = new AlteracaoEstatuariaWS();
     * $ae->data = '04/05/2017 10:22:23';
     * $ae->texto = 'Texto da alteração';
     *
     * $aaep = new AreaAtuacaoEntidadePrivadaWS()
     * $aaep->codigo = '0000'
     *      ->descricao = 'Descrição da área de atuação';
     *
     * $ocws = new RespostaCertidaoWS();
     * $ocws->dataValidade = '02/03/2005 14:03:20'
     *      ->nomeCertidao = 'Nome'
     *      ->possuiCertidao = true;
     *
     * $cws = new CertidaoWS();
     * $cws->fgtsDataEmissao = '02/03/2005 14:03:20'
     *     ->fgtsDataValidade = '02/03/2015 14:03:20'
     *     ->fgtsHora = '00000'
     *     ->fgtsIsento = true
     *     ->fgtsNumero = 'xxxxxxxxxxxx'
     *     ->fgtsTipo = 'xxxx'
     *     ->inssDataEmissao = '02/03/2005 14:03:20'
     *     ->inssDataValidade = '02/03/2005 14:03:20'
     *     ->inssHora = 'xxxxx'
     *     ->inssIsento = true
     *     ->inssNumero = 'xxxxxxxxxxxxxx'
     *     ->inssTipo = 'xxxx'
     *     ->outrasCertidoesWS = $ocws
     *     ->receitaEstadualDataEmissao = ''
     *     ->receitaEstadualDataValidade = ''
     *     ->receitaEstadualHora = ''
     *     ->receitaEstadualIsento = true
     *     ->receitaEstadualNumero = 'xxxxxxxxxxxxxx'
     *     ->receitaEstadualTipo = 'xxxxxx'
     *     ->receitaMunicipalDataEmissao = ''
     *     ->receitaMunicipalDataValidade = ''
     *     ->receitaMunicipalHora = 'xxxxx'
     *     ->receitaMunicipalIsento = true
     *     ->receitaMunicipalNumero = 'xxxxxxxxxxxxxxxxxxx'
     *     ->receitaMunicipalTipo = 'xxxxxx'
     *     ->srfPgfnDataEmissao = ''
     *     ->srfPgfnDataValidade = ''
     *     ->srfPgfnHora = 'xxxxx'
     *     ->srfPgfnIsento = true
     *     ->srfPgfnNumero = 'xxxxxxx'
     *     ->srfPgfnTipo = 'xxxxxx';
     *
     * $cnaews = new CnaeWS()
     * $cnaews->codigo = 'xxxx'
     *        ->descricao = 'xxxxxxxxxxxx';
     *
     * $dws = new DirigenteWS();
     * $dws->cargo = 'xxxxx'
     *     ->cpf = 'xxxxxxxxxxx'
     *     ->nome = 'xxxxxxxxxxxxxxxxxx'
     *     ->orgaoExpedidor = 'xxx'
     *     ->profissao = 'xxxxxxxxxxxx'
     *     ->rg = 'xxxxxxxx';
     *
     * $dnd = new DeclaracaoNaoDividaWS()
     * $dnd->dataAssinatura = ''
     *     ->dirigenteSignatarioWS = $dws;
     *
     * $eaws = new EsferaAdministrativaWS();
     * $eaws->codigo = 'xxxxx';
     *
     * $ufws = new UnidadeFederativaWS();
     * $ufws->sigla = 'DF';
     *
     * $munws = new MunicipioWS()
     * $munws->codigo = 'xxxxx'
     *       ->nome = 'xxxxx'
     *       ->unidadeFederativaWS = $ufws;
     *
     * $estws = new EstatutoWS();
     * $estws->cartorio = 'xxxxxx'
     *       ->dataRegistro = ''
     *       ->livroFolha = 'xxxxx'
     *       ->municipioWS = $munws
     *       ->numeroDoRegistroMatricula = 'xxxxxxx'
     *       ->transcricaoEstatuto = 'xxxxxx';
     *
     * $respws = new MembroParticipanteWS()
     * $respws->ativoNoSistema = true
     *        ->cargoFuncao = 'xxxxxx'
     *        ->cep = 'xxxxxxxx'
     *        ->cpf = 'xxxxxxxxxxx'
     *        ->email = 'xxxxxxxxx'
     *        ->endereco = 'xxxxxx'
     *        ->matricula = 'xxxxxxxxx'
     *        ->municipioMembroWS = $munws
     *        ->municipioWS = $munws
     *        ->nome = 'xxxxxxx'
     *        ->orgaoExpedidor = 'xxx'
     *        ->rg = '00000000'
     *        ->senha = 'xxxxxxxx'
     *        ->telefone = '00000000000';
     *
     * $tpip = new TipoIdentificadorParticipeWS();
     * $tpip->codigo = 'xxxxx';
     *
     * $ppws = new ParticipantesPropostaWS();
     * $ppws->agencia = '0000000'
     *      ->alteracaoEstatuaria = $ae
     *      ->aprovado = true
     *      ->areaAtuacaoEntidadePrivada = $aaep
     *      ->bairroDistrito = 'bairro'
     *      ->cep = '00000000'
     *      ->certidaoAprovada = true
     *      ->certidaoWS = $cws
     *      ->cienteDirigenteNaoRemunerado = false
     *      ->cnaePrimario = 'xxxxxxxxxx'
     *      ->cnaePrimarioWS = $cnaews
     *      ->codigoBanco = 'xxxx'
     *      ->codigoErroSiafi = 'xxxx'
     *      ->consorcioPublico = true
     *      ->contaCorrente = 'xxxxxxxx'
     *      ->cpfUsuarioAprovou = 'xxxxxxxxxxx'
     *      ->declaracaoAprovado = true
     *      ->declaracaoNaoDividaWS = $dnd
     *      ->email = 'xxxxxxxxxxxx'
     *      ->endereco = 'xxxxxxxxxxx'
     *      ->entidadesVinculadas = 'xxxxxxxxxxxxxx'
     *      ->erroSiafi = 'xxxxxxxxxxxxx'
     *      ->esferaAdministrativaWS = $eaws
     *      ->estatutoAprovado = true
     *      ->estatutoWS = $estws
     *      ->executor = true
     *      ->identificacao = 'xxxxx'
     *      ->inscricaoEstadual = 'xxxx'
     *      ->inscricaoMunicipal = 'xxxxxx'
     *      ->interveniente = true
     *      ->mandatario = true
     *      ->municWS = $munws
     *      ->naturezaJuridica = 'xxxxx'
     *      ->nome = 'xxxxxxxx'
     *      ->nomeFantasia = 'xxxxxxxx'
     *      ->proponente = true
     *      ->quadroDirigenteAprovado = true
     *      ->representanteWS = $respws
     *      ->respExercicioWS = $respws
     *      ->responsavelWS = $respws
     *      ->respostaEnvioSiafi = 'xxxxxx'
     *      ->statusEnvioSiafi = 'xxxx'
     *      ->telefone = 'xxxxxxx'
     *      ->telexFaxCaixaPostal = 'xxxxx'
     *      ->tipoIdentificacaoWS = $tpip
     *      ->transcricaoEstatutoONG = 'xxxxxxxxx';

     * $respws = new ResponsaveisConvenioWS();
     * $respws->membroWS = $membro
     *        ->participeWS = $ppws
     *
     * $concedente = new OrgaoAdministrativoWS();
     * $concedente->codigo = '000000'
     *            ->responsavelWS = $respws;
     *
     * $modalidade = new ModalidadeConvenioWS();
     * $modalidade->valor = '000000';
     *
     * $prog = new ProgramaWS();
     * $prog->acaoOrcamentaria = 'xxxxxxx'
     *      ->aceitaDespesaAdministrativa = true
     *      ->aceitaPropostaDeProponenteNaoCadastrado = true
     *      ->chamamentoPublico = true
     *      ->cnpjBeneficiarioEspecificoWS = $cnpjBeneficiario
     *      ->cnpjProgramaEmendaWS = $cnpjProgEmenda
     *      ->codigo = 'xxxxx'
     *      ->concedente = $concedente
     *      ->criteriosDeSelecao = 'xxxxxx'
     *      ->dataDisponibilizacao = '00/00/0000 00:00:00'
     *      ->dataFimBeneficiarioEspecifico = '00/00/0000 00:00:00'
     *      ->dataFimEmendaParlamentar = '00/00/0000 00:00:00'
     *      ->dataFimPropostaVoluntaria = '00/00/0000 00:00:00'
     *      ->dataInicioBeneficiarioEspecifico = '00/00/0000 00:00:00'
     *      ->dataInicioEmendaParlamentar = '00/00/0000 00:00:00'
     *      ->dataInicioPropostaVoluntaria = '00/00/0000 00:00:00'
     *      ->dataPublicacaoImprensa = '00/00/0000 00:00:00'
     *      ->descricao = 'xxxxxxxxx'
     *      ->estadosHabilitados = $ufws
     *      ->executor = $concedente
     *      ->modalidades = $modalidade
     *      ->naturezasJuridicas =
     *      ->nome = 'xxxxxxx'
     *      ->numeroDocumento = 'xxxxx'
     *      ->objetoWS =
     *      ->obrigaPlanoTrabalho = true
     *      ->observacao = 'xxxxxxxxxxx'
     *      ->publicadoImprensa = true
     *      ->qualificacaoBeneficiarioEmendaParlamentar = true
     *      ->qualificacaoBeneficiarioEspecifico = true
     *      ->qualificacaoPropostaVoluntaria = true
     *      ->regrasWS =
     *      ->status = 'xxxxx';
     *
     * $ppws = new PropostaProgramaWS();
     * $ppws->objetoWS = $obj
     *      ->origemRecursoPropProgramaWS = $orpp
     *      ->ProgramaWS =
     *      ->qualificacaoProponenteWS =
     *      ->regrasWS =
     *      ->valorContrapartida = 00.0000
     *      ->valorContrapartidaBensServicos = 00.0000
     *      ->valorContrapartidaFinanceira = 00.0000
     *      ->valorGlobal = 00.0000;
     *
     * $bsp = new BensServicosPropostaWS();
     * $bsp->cep = '00000000'
     *     ->descricao = 'xxxxxxxx'
     *     ->despesaCompartilhada = true
     *     ->endereco = 'xxxxxxxxxxxxx'
     *     ->justificativaAnalista = 'xxxxxxxxxx'
     *     ->municipioWS = $munws
     *     ->naturezaAquisicaoWS = $na
     *     ->naturezaDespesaSubItemWS = $ndsi
     *     ->observacao = 'xxxxxxxx'
     *     ->propostaProgramaWS = $ppws
     *     ->
     *
     * // -- INSTANCIA A CLASSE COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $dados = new PropostaWS();
     * $dados->ano = '2016'
     *       ->atribuicaoRespAnalise = 'xxxxxx'
     *       ->bensServicoWS =
     *       ->
     *
     * $propostas = $siconv->enviaProposta($dados);     * </code>
     *
     * @param PropostaWS $dados
     * @return mixed
     */
    public function enviaProposta(PropostaWS $dados)
    {
        $con = new enviaProposta();
        $con->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('enviaProposta', array($con), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    /**
     * Função que exporta os dados do convênio.
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * // -- INSTANCIA A CLASSE COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $dados = new ConvenioExportacaoWS();
     * $dados->codigoOrgaoConcedente = '26000'
     *       ->ano = '2016'
     *       ->sequencial = 12553;
     *
     * $propostas = $siconv->exportaConvenio($dados);
     * </code>
     *
     * @param ConvenioExportacaoWS $dados
     * @return mixed
     */
    public function exportaConvenio(ConvenioExportacaoWS $dados)
    {
        $con = new exportaConvenio();
        $con->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('exportaConvenio', array($con), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    /**
     * Função que exporta os dados do proponente.
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * // -- INSTANCIA A CLASSE COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $dados = new ProponenteExportacaoWS();
     * $dados->identificacao = 'xxxxx';
     * $dados->tipoIdentificacao = 'xxxxxxxx';
     *
     * $propostas = $siconv->exportaProponente($dados);
     * </code>
     *
     * @param ProponenteExportacaoWS $dados
     * @return mixed
     */
    public function exportaProponente(ProponenteExportacaoWS $dados)
    {
        $con = new exportaProponente();
        $con->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('exportaProponente', array($con), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    /**
     * Função que exporta todas as informações da proposta a partir do ano, do órgão e do número da proposta.
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * // -- INSTANCIA A CLASSE COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $dados = new PropostaExportacaoWS();
     * $dados->codigoOrgaoConcedente = '26298'
     *       ->ano = '2016'
     *       ->sequencial = 34694;
     *
     * $propostas = $siconv->exportaProposta($dados);
     * </code>
     *
     * @param array $args
     * @return mixed
     */
    public function exportaProposta(PropostaExportacaoWS $dados)
    {
        $con = new exportaProposta();
        $con->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('exportaProposta', array($con), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    public function obterVersaoWebService($obterVersaoWebService = null)
    {
        return $this->getSoapClient()->call('obterVersaoWebService', array($obterVersaoWebService), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }

    /**
     * Função que rejeita o impedimento técnico da proposta
     *
     * @example
     * <code>
     * // -- INSTANCIA A CLASSE Ws_Siconv();
     * $siconv = new Ws_Siconv();
     *
     * // -- INSTANCIA A CLASSE COM OS ATRIBUTOS NECESSÁRIOS PARA O ENVIO AO SICONV
     * $dados = new ImpedimentoTecnicoPropostaWS();
     * $dados->anoProposta = 2016;
     *       ->codigoImpedimentoTecnico = 'xxxxxxx'
     *       ->codigoOrgaoConcedenteProposta = 'xxxxxxxx'
     *       ->
     *
     * $propostas = $siconv->rejeitarPropostaImpedimentoTecnico($dados);
     * </code>
     *
     * @param ImpedimentoTecnicoPropostaWS $dados
     * @return mixed
     */
    public function rejeitarPropostaImpedimentoTecnico(ImpedimentoTecnicoPropostaWS $dados)
    {
        $con = new RejeitarPropostaImpedimentoTecnico();
        $con->arg0 = $this->trataAtributos($dados);

        return $this->getSoapClient()->call('rejeitarPropostaImpedimentoTecnico', array($con), [
            'uri' => 'http://interfaceSiconv.cs.siconv.mp.gov.br/',
            'soapaction' => ''
        ]);
    }
}