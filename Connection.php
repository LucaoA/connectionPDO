<?php

/**
 * Classe que facilita a conexao com o banco de 
 * dados e sua manipulacao.
 * 
 */
class Connection {

    /**
     * Instancia da propria classe
     * @var Connection
     */
    private static $connection;

    /**
     * Instancia de \PDO
     * @var \PDO
     */
    private $dbh;

    /**
     * Statament da ultima instrucao SQL executada
     * @var \PDOStatement
     */
    private $stmt;


    /**
     * Primeira nome de conexao informado.
     * @var string
     */
    private static $firstKey;


    /**
     * Metodo construtor que instancia classe PDO e faz a conexao com o banco de dados
     * 
     * @param string $host
     * @param string $dbname
     * @param string $user
     * @param string $pass
     *
     * @throws \PDOException - Ira disparar esta exception caso tenha problemas para se conectar com o banco de dados
     */
    public function __construct($host, $dbname, $user, $pass) {
        $dsn = "mysql:host={$host};dbname={$dbname};charset=UTF8";
        //$dsn = "mysql:host={$host};dbname={$dbname};";
        $options = [
            \PDO::ATTR_PERSISTENT => true,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ];
        $this->dbh = new \PDO($dsn, $user, $pass, $options);
    }


    public static function getAllInstance(){
        return self::$connection;
    }


    /**
     * Busca a instancia da conexao informada, e caso nao seja informado o nome da instancia
     * busca a primeira instanciada.
     * 
     * @param  string $name Nome que foi dado a conexao
     * @return Connection
     */
    public static function getInstance($name = null){
        if(empty($name)){
            if(is_array(self::$connection) && pos(self::$connection) instanceof Connection){
                return pos(self::$connection);
            }elseif(!is_array(self::$connection) && self::$connection instanceof Connection){
                return pos(self::$connection);
            }
        }elseif(isset(self::$connection[$name]) && self::$connection[$name] instanceof Connection){
            return self::$connection[$name];
        }
        throw new \InvalidArgumentException("Instancia de conexão não encontrada");
    }


    /**
     * Conecta ao banco de dados de acordo com a configuracao informada, retornando
     * a instancia da classe PDO ja conectado ao banco, em caso de erro retorna false.
     *
     * @param array $configs Configuracoes de banco de dados
     * Ex de array de conexao:
     * [
     *     "nome da conexao" => [
     *         host => Host do banco de dados
     *         dbname => Nome do banco dedados
     *         user => Usuario do banco de dados
     *         password => Senha do banco de dados
     *     ], ...
     * ]
     *
     * @throws \PDOException - Ira disparar esta exception caso tenha problemas para se conectar com o banco de dados
     * 
     * @return Connection
     */
    public static function connection($configs){
        foreach ($configs as $name => $config){
            if(!isset(self::$connection[$name]) || !(self::$connection[$name] instanceof Connection)){
                if(empty($config['host']) || empty($config['name']) || empty($config['user']) || empty($config['pass'])){
                    throw new \InvalidArgumentException("Parâmetros de conexão inválidos");
                }
                self::$connection[$name] = new Connection($config['host'], $config['name'], $config['user'], $config['pass']);
                if(empty(self::$firstKey)){
                    self::$firstKey = $name;
                }
            }
        }

        return self::$connection;
    }


    /**
     * Recebe um array associativo e monta parte do 
     * SQL de update, especificamente o SET
     * 
     * @param array $update
     * @return string
     */
    public function createSet($update) {
        return implode(', ', array_map(function ($v, $k) {
                    return sprintf("%s = :%s", $k, $k);
                }, $update, array_keys($update)));
    }


    /**
     * Recebe um array associativo e monta parte do 
     * SQL de um where
     * 
     * @param array $where
     * @return string
     */
    public function createWhere($where) {
        return implode(' AND ', array_map(function ($v, $k) {
                    if(is_array($v)){
                        $in = [];
                        $v = array_values($v);
                        foreach ($v as $key => $value) {
                            $in[] = " :{$k}_{$key} ";
                        }
                        return $k." IN (".implode(',', $in).")";
                    }
                    return sprintf("%s = :%s", $k, $k);
                }, $where, array_keys($where)));
    }


    /**
     * Recebe um array associativo e monta parte do 
     * SQL de um insert
     * 
     * @param array $insert
     * @return string
     */
    public function createInsert($insert) {
        $campos = implode(', ', array_keys($insert));
        $values = ':' . implode(',:', array_keys($insert));
        return "({$campos}) VALUES ({$values})";
    }    

    /**
     * Executa o prepare do PDO jogando a statament retornado em
     * uma propriedade da classe.
     * 
     * @param  string $query
     *
     * @throws \PDOException
     * 
     * @return \PDOStatement
     */
    public function query($query) {
        return $this->stmt = $this->dbh->prepare($query);
    }


    /**
     * Verifica tipo do valor do parametro passado e seta o 
     * mesmo para monta a consulta corretamente.
     * 
     * @param  array $params
     * @param  int $type
     */
    public function bind($params, $type = null) {
        foreach ($params as $param => $value) {
            if (is_null($type)) {
                if(is_array($value)){
                    $value = array_values($value);
                    foreach ($value as $key => $val) {
                        $this->stmt->bindValue("{$param}_{$key}", $val);
                    }
                    continue;
                }
                $type = PDO::PARAM_STR;
                /*switch (true) {
                    case is_int($value):
                        $type = PDO::PARAM_INT;
                        break;
                    case is_bool($value):
                        $type = PDO::PARAM_BOOL;
                        break;
                    case is_null($value):
                        $type = PDO::PARAM_NULL;
                        break;
                    default:
                        $type = PDO::PARAM_STR;
                }*/
            }
            $this->stmt->bindValue($param, $value, $type);
        }
    }


    /**
     * Executa a instrucao preparada no statament atual.
     * @return bool
     */
    public function execute() {
        return $this->stmt->execute();
    }


    /**
     * Conta o numero de linhas retornada ou afetadas pelo
     * statament executado.
     * @return int
     */
    public function rowCount() {
        return $this->stmt->rowCount();
    }


    /**
     * Despeja a informação contida por uma declaração preparada diretamente na saída. 
     * Ele irá fornecer a consulta SQL em uso, o número de parâmetros utilizados (Params), 
     * a lista de parâmetros com o seu nome da chave ou posição, seu nome, sua posição na consulta 
     * (se for suportado pelo driver PDO, caso contrário, será -1), tipo (param_type) como um inteiro, e um valor booleano is_param.
     */
    public function debugDumpParams() {
        $this->stmt->debugDumpParams();
    }    


    /**
     * Retorna o ultima ID gerado pela conexao atual.
     * @return string
     */
    public function lastInsertId() {
        return $this->dbh->lastInsertId();
    }


    /**
     * Define o commit como OFF e abre transacao com o 
     * banco para executar SQLs podendo dar rollBack ou commit.
     * @return bool
     */
    public function beginTransaction() {
        return $this->dbh->beginTransaction();
    }


    /**
     * Concretiza as instrucoes SQLs executadas depois Connection::beginTransaction
     * @return bool
     */
    public function commit() {
        return $this->dbh->commit();
    }


    /**
     * Descartas as instrucoes SQLs executadas depois Connection::beginTransaction
     * @return bool
     */
    public function rollBack() {
        return $this->dbh->rollBack();
    }


    /**
     * Executa query e retorna seu statament
     * 
     * @param  string $query
     * @param  array $params Parametros para o bind
     *
     * @throws \PDOException
     * 
     * @return PDOStatement
     */
    public function executeQuery($query, $params = null) {
        $this->query($query);
        if (!empty($params)) {
            $this->bind($params);
        }
        $this->execute();
        return $this->stmt;
    }


    /**
     * Executa query e retorna o numero de linhas afetadas.
     * 
     * @param  string $query
     * @param  array $params Parametros para o bind
     *
     * @throws \PDOException
     * 
     * @return int
     */
    public function executeUpdate($query, $params = null) {
        $this->query($query);
        if (!empty($params)) {
            $this->bind($params);
        }
        $this->execute();
        return $this->rowCount();
    }    


    /**
     * Executa um update na tabela informada no campos informados com
     * a condicao informada.
     * 
     * @param string $table
     * @param array $update
     * @param array $where
     *
     * @throws \PDOException
     * 
     * @return \PDOStatement
     */
    public function update($table, $update, $where) {
        $query = "UPDATE {$table} SET {$this->createSet($update)} ";
        if (!empty($where)) {
            $query .= " WHERE {$this->createWhere($where)}";
        }
        $this->query($query);
        $this->bind(array_merge($where, $update));
        $this->execute();
        return $this->rowCount();
    }


    /**
     * Executa um insert na tabela informada com os dados informados.
     * 
     * @param string $table
     * @param array $insert
     *
     * @throws \PDOException
     * 
     * @return \PDOStatement
     */
    public function insert($table, $insert) {
        $query = "INSERT INTO {$table} {$this->createInsert($insert)}";
        $this->query($query);
        $this->bind($insert);
        $this->execute();
        return $this->lastInsertId();
    }


    /**
     * Executa um insert ignore na tabela informada com os dados informados.
     * 
     * @param string $table
     * @param array $insert
     *
     * @throws \PDOException
     * 
     * @return \PDOStatement
     */
    public function insertIgnore($table, $insert) {
        $query = "INSERT IGNORE INTO {$table} {$this->createInsert($insert)}";
        $this->query($query);
        $this->bind($insert);
        $this->execute();
        return $this->lastInsertId();
    }


    /**
     * Deleta registros da tabela informada, 
     * de acordo com a condicao passada.
     * 
     * @param string $table
     * @param array $where
     *
     * @throws \PDOException
     * 
     * @return \PDOStatement
     */
    public function delete($table, $where) {
        $query = "DELETE FROM {$table} WHERE {$this->createWhere($where)}";
        $this->query($query);
        $this->bind($where);
        $this->execute();
        return $this->rowCount();
    }


    /**
     * Executa a consulta e retorna um array com os resultados.
     * 
     * @param string $query
     * @param array $params
     *
     * @throws \PDOException
     * 
     * @return array
     */
    public function fetchAll($query, $params = null) {
        $this->query($query);
        if (!empty($params)) {            
            $this->bind($params);
        }
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Executa a consulta e retorna a primeira linha somente.
     * 
     * @param string $query
     * @param array $params
     *
     * @throws \PDOException
     * 
     * @return array
     */
    public function fetchRow($query, $params = null) {
        $this->query($query);
        if (!empty($params)) {
            $this->bind($params);
        }
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

}
