<?php
    
    
    
    /*********************
     *                   *
     *                   *
     *   Inflater Class  *
     *                   *
     *                   *
     *********************/
    
    
    
    // this class will handle how to render pages
    
    class Inflater {
        
        private $template_folder = null;
        private $css_folder = null;
        private $js_folder = null;
        private $static_folder = null;
        private $args = null;
        
        function __construct(){
            
            // initiating variables
            
            $this->template_folder = "templates/";
            $this->css_folder = "static/css/";
            $this->js_folder = "static/js/";
            $this->static_folder = "static/";
            $this->args = [];
            
            // collecting the "css" files from "css" folder
            
            $css_folder = $this->css_folder;
            
            if(true || file_exists($css_folder) && is_dir($css_folder)){
                $css_files = "";
                foreach (scandir($css_folder) as $v){
                    if(preg_match("/\S+(\.css)/",$v)){
                        $ext_less = str_replace(".css","",$v);
                        $css_files = $css_files."public $".$ext_less."='".$this->root_uri().$css_folder.$v."';";
                        eval('$css_files_obj = new class{'.$css_files.'};');
                        $this->args["_CSS"] = $css_files_obj;
                    }
                }
            }
            
            // collecting the "js" files from "js" folder
            
            $js_folder = $this->js_folder;
            if(file_exists($js_folder) && is_dir($js_folder)){
                $js_files = "";
                foreach (scandir($js_folder) as $v){
                    if(preg_match("/\S+(.js)/",$v)){
                        $ext_less = str_replace(".js","",$v);
                        $js_files = $js_files."public $".$ext_less."='".$this->root_uri().$js_folder.$v."';";
                        eval('$js_files_obj = new class{'.$js_files.'};');
                        $this->args["_JS"] = $js_files_obj;
                    }
                }
            }
            
            
            
            // will return the site root url
            $this->args["_ROOT"] = $this->root_uri();
            
            // returns the static folder path if empty param given else returns the combination
            $this->args["static_url"] = function (string $path=""){
                                            $url = $this->root_uri().$this->static_folder."/".preg_replace("/[\/]{2,}/","/",$path);
                                            return $url;
                                        };
            

            
        }
        
        private function root_uri(){
            $scheme = $_SERVER["REQUEST_SCHEME"];
            $host = $_SERVER["HTTP_HOST"];
            $url = $host."/";
            $proto = (empty($proto)) ? "http://":$proto."://";
            $uri = $proto.preg_replace("/(\/){2,}/","/",$url);
            return $uri;
        }
        
        
        // to pass data as "php data" like "$variable"
        function parse_args(array $args=array(),bool $escape=true){
            foreach ($args as $k=>$v){
                $v = ($escape && is_array($v) && gettype($v)!="object") ? htmlspecialchars($v):$v;
                $this->args[$k] = $v;
            }
        }
        
        
        // to flush text messages to the page as
        function flush(string $msg){
            $this->args["flushes"][] = $msg;
            $this->args["is_flushed"] = (count($this->args["flushes"])>0) ? true:false;
        }
        
        
        // to render the given page
        function inflate(string $file_name,array $args=[],bool $escape=false) { 
            
            $this->parse_args($args,$escape);
            
            $file_path = $this->template_folder."//".$file_name.".html";
            $file_html = preg_replace("/(\/{2,}|\s)/","/",$file_path);
            $file_htm = str_replace(".html",".htm",$file_html);
            
            if(file_exists($file_html) || file_exists($file_htm)){
                
                // extract all array keys to php variable
                extract($this->args);
                
                $file = (file_exists($file_html)) ? $file_html:$file_htm;
                
                // get the contents from the given file
                $html = file_get_contents($file);
                
                
                // check for the existence of "extends" keyword
                if(preg_match("/({%\s*extends\s*(\"|').*(\"|')\s*%})/",$html,$extends)){
                    
                    $extended_file = $this->template_folder.preg_replace("/({%\s*extends\s*(\"|'))|(\s*\"\s*%})/","",$extends[0]);
                    if(file_exists($extended_file)){
                        
                        $template = file_get_contents($extended_file);
                        
                        // to check if the "block-endblock" exists in the template file
                        if(preg_match("/({%\s*block\s*%})[\t\n\v\s\S\w\W]*({%\s*endblock\s*%})/",$template,$parent_block)){
                            $child_block = preg_replace("/({%\s*extends\s*(\"|').*(\"|')\s*%})/","",$html);
                            $html = preg_replace("/({%\s*block\s*%})[\t\n\v\s\S\w\W]*({%\s*endblock\s*%})/",$child_block,$template);
                        }else{
                            die("No {% block %} {% endblock %} found in the template file!");
                        }
                    }else{
                        die("Template File - $extended_file not found!");
                    }
                }
                
                
                // removing {% block %} {% endblock %}
                
                $html_ = preg_replace("/({%\s*block\s*%})|({%\s*endblock\s*%})/m","",$html);
                
                
                // removing all single quotes
                $html_ = preg_replace("/='(?=\w*)/",'="',$html_);
                $html_ = preg_replace("/(?<=(\w|\s))'(?=[^)\s])/",'"',$html_);
                
                // covering all the html using single quotes to treat as php code
                $html_ = "echo '$html_';";
                
                // this will treat "{{ ... }}" as "echo ...;"
                $html_ = str_replace("{{","';echo ",str_replace("}}",";echo '",$html_));
                
                // replacing the logics to php code to make them executable
                $html_ = preg_replace("/({%)\s*if\s*/","';if(",$html_);
                $html_ = preg_replace("/({%)\s*foreach\s*/","'; foreach(",$html_);
                $html_ = preg_replace("/({%)\s*for\s*/","'; for(",$html_);
                $html_ = preg_replace("/({%)\s*while\s*/","'; while(",$html_);
                $html_ = preg_replace("/({%)\s*endforeach\s*(%})/","'; } echo '",$html_);
                $html_ = preg_replace("/({%)\s*endfor\s*(%})/","';} echo '",$html_);
                $html_ = preg_replace("/({%)\s*endwhile\s*(%})/","'; } echo '",$html_);
                $html_ = preg_replace("/({%)\s*endif\s*(%})/","'; } echo '",$html_);
                $html_ = preg_replace("/({%\s*end[^%]*\s*%})/","'; } echo '",$html_);
                $html_ = preg_replace("/({%)\s*elif\s*/","';}else if(",$html_);
                $html_ = preg_replace("/:\s*(%})/","){echo '",$html_);
                $html_ = preg_replace("/({%)/","'; ",$html_);
                $html_ = preg_replace("/(%})/",";echo '",$html_);
                
                
                // removing all "{{ ... }"
                $html_ = preg_replace("/echo\s*[^}']*\}/","echo '",$html_);
                
                // removing all "{ ... }}"
                $html_ = preg_replace("/(?<=[^)])\s*\{\s*[^;]*;/","';",$html_);
                
                // executing all the codes as php script
                
                eval($html_);
                
            }else{
                throw new Exception("File $file_path not found!");
            }
        }
        
        
        
        
    } // end of "Inflater" class
    
    
    
    
    /*********************
     *                   *
     *                   *
     *      DB Class     *
     *                   *
     *                   *
     *********************/
    
    
    
    
    // this is the base class to manage SQL Databases
    
    class DB {
        
        protected $conn = null,
                $driver,$host,
                $port,$charset,
                $db,$user,$pass;
        public const
                OBJ = PDO::FETCH_OBJ,
                ASSOC = PDO::FETCH_ASSOC,
                IND = PDO::FETCH_NUM;
                
        
        function __construct(array $info){
            
            $this->driver = $info["driver"];
            $this->host = $info["host"];
            $this->user = $info["user"];
            $this->pass = $info["pass"];
            $this->db = $info["dbname"];
            $this->port = $info["port"];
            $this->charset = $info["charset"];
            
            
            $this->connect();
            
        }
        
        // to disconnect
        function disconnect(){
            $this->conn = null;
            return true;
        }
        
        // to connect if not connected
        function connect(){
            try{
                if($this->conn==null){
                    
                    $port = (empty($this->port)) ? "":"port=$this->port;";
                    $charset = (empty($this->charset)) ? "":"charset=$this->charset;";
                    
                    $this->conn = new PDO("$this->driver:host=$this->host;$port$charset",$this->user,$this->pass) or die("Error in Connection Building!\nCheck the information you've given on 'DB' setup!");
                    $this->conn->exec("create database if not exists $this->db");
                    $this->conn->exec("use $this->db");
                    
                    return true;
                }
            }catch(Exception $e){} 
        }
        
        // return ture/false based on connectivity
        function is_connected(){ return ($this->conn!=null); }
        
        
        function new_table(string $table_name){
            $this->connect();
            return new class($table_name,$this->conn,$this) {
                
                private $columns = array(),
                        $col_names = array(),
                        $table_name = null;
                
                function __construct(string $table_name,$conn,$db_class){
                    $this->table_name = $table_name;
                    $this->conn = $conn;
                    $this->db_class = $db_class;
                }
                
                
                # thia will return specific data type and length steing of sql
                function int(int $l){ return "integer($l)"; }
                function str(int $l){ return "varchar($l)"; }
                function txt(){ return "text"; }
                function dat(){ return "date"; }
                function datime(){ return "datetime"; }
                function bool(){ return "enum('0','1')"; }
                
                
                function col(string $name,string $type_and_length,bool $is_primary=false,bool $is_not_null=false,bool $is_unique=false){
                    
                    $is_primary = ($is_primary) ? "primary key auto_increment":"";
                    $is_not_null = ($is_not_null) ? "not null":"";
                    $is_unique = (!$is_primary && $is_unique) ? "unique":"";
                    
                    $q = "$name $type_and_length $is_primary $is_not_null $is_unique";
                    
                    $this->col_names[] = $name;
                    $this->columns[$name] = $q;
                    
                    return $this;
                }
                
                        
                function add(string $name,string $type_and_length,bool $is_primary=false,bool $is_not_null=false,bool $is_unique=false){
                    
                    $is_primary = ($is_primary) ? "primary key auto_increment":"";
                    $is_not_null = ($is_not_null) ? "not null":"";
                    $is_unique = (!$is_primary && $is_unique) ? "unique":"";
                    
                    $q = "$name $type_and_length $is_primary $is_not_null $is_unique";
                    $this->conn->exec("alter table $this->table_name add $q");
                    
                    $this->col_names[] = $name;
                    $this->columns[$name] = $q;
                    
                    return $this;
                }
                
                function drop(string $name){
                    $this->conn->exec("alter table $this->table_name drop $name");
                    unset($this->col_names[array_search($name,$this->col_names)]);
                    unset($this->columns[$name]);
                    return $this;
                }
                
                
                // will drop the whole table
                function drop_all(){
                    $this->conn->exec("drop table $this->table_name");
                    $this->columns = array();
                    $this->col_names = array();
                    return $this;
                }
                
                
                function insert(array $data){
                    if(count($data)>0){
                        $cols = "";$keys = "";$params = array();
                        
                        foreach($data as $k=>$v){
                            $end = ($v==end($data)) ? "":",";
                            $cols = $cols.$k.$end;
                            $keys = $keys.":$k".$end;
                            $params[":$k"] = $v;
                        }
                        
                        $q = "insert into $this->table_name ($cols) values ($keys)";
                        $result = $this->conn->prepare($q);
                        $result->execute($params);
                        return $result->rowCount();
                    }else{return 0;}
                }
                
                
                function delete(array $data){
                    if(count($data)>0){
                        $keys = "where ";$params = array();
                        foreach($data as $k=>$v){
                            $end = ($v==end($data)) ? "":" && ";
                            $keys = $keys."$k=:$k".$end;
                            $params[":$k"] = $v;
                        }
                        
                        $q = "delete from $this->table_name $keys";
                        $result = $this->conn->prepare($q);
                        $result->execute($params);
                        return $result->rowCount();
                    }else{return 0;}
                }
                
                
                function update(array $conditions,array $data){
                    $conds = "where ";
                    $cols = "";
                    $params = array();
                    
                    foreach($conditions as $k=>$v){
                        $end = ($v==end($conditions)) ? "":" && ";
                        $conds = $conds.$k."=".":$k"."_CONDITION$end";
                        $params[":$k"."_CONDITION"] = $v;
                    }
                    
                    foreach($data as $col=>$val){
                        $end = ($val==end($data)) ? "":",";
                        $cols = $cols."$col=:$col $end";
                        $params[":$col"] = $val;
                    }
                    
                    $q = "update $this->table_name set $cols $conds";
                    $result = $this->conn->prepare($q);
                    $result->execute($params);
                    return $result->rowCount();
                    
                }
                
                
                function fetch(array $conditions=[],$return_type = DB::ASSOC){
                    
                    $conds = null;
                    $params = array();
                    $order_by = (!empty($conditions["ORDER_BY"])) ? "order by ".$conditions['ORDER_BY']:"";
                    $limit = (!empty($conditions["LIMIT"])) ? "limit ".$conditions['LIMIT']:"";
                    
                    foreach($conditions as $k=>$v){
                        unset($conditions["ORDER_BY"]);
                        unset($conditions["LIMIT"]);
                        if($k!="ORDER_BY" && $k!="LIMIT"){
                            $end = ($v==end($conditions) ) ? "":" && ";
                            $conds = $conds."$k=:$k"."$end";
                            $params[":$k"] = $v;
                        }
                    }
                    
                    $conds = (!empty($conds)) ? "where $conds":"";
                    
                    $q = "select * from $this->table_name $conds $order_by $limit";
                    $result = $this->conn->prepare($q);
                    $result->execute($params);
                    $fetched_data = $result->fetchAll($return_type);
                    
                    return new class($fetched_data){
                        private $rows;
                        function __construct($rows){
                            $this->rows = $rows;
                        }
                        
                        function each($func,bool $reverse=false){
                            $rows = ($reverse) ? array_reverse($this->rows):$this->rows;
                            foreach($rows as $ind=>$row){
                                $func($row,$ind);
                            }
                            return $this;
                        }
                        
                        function first(){return $this->rows[0];}
                        
                        function last(){return end($this->rows);}
                        
                        function get(int $index){return $this->rows[$index];}
                        
                    };
                    
                
                }
                
                
                // will create the table and all the columns added by 'col' function
                function create(){
                    $q = null;
                    foreach($this->columns as $i=>$v){
                        $end = ($v==end($this->columns)) ? "":",";
                        $q = $q.$v.$end;
                    }
                    
                    $query = "create table if not exists $this->table_name ($q)";
                    
                    $this->conn->exec($query);
                    return $this->db_class;
                }
                
            };
        }
    
    
    
    } // end of "DB" class
    
    
    
    
    
    /*********************
     *                   *
     *                   *
     *    WebApp Class   *
     *                   *
     *                   *
     *********************/
    
    
    
    // the WebApp class to create a web application
    
    class WebApp extends Inflater {
        
        private $routes = [];
        private $error_404_page = null;
        private $db_obj = null;
        
        function __construct(){
            
            // calling super constructor
            parent::__construct();
            
            $htaccess = ".htaccess";
            $script = "
            RewriteEngine on\n
            RewriteCond %{REQUEST_FILENAME} -d\n
            RewriteCond %{REQUEST_FILENAME} -f\n
            RewriteRule ^(.+)$ index.php/".'$1'." [L]";
            
            // if already a .htaccess file exists
            if(file_exists($htaccess) && !file_exists($htaccess."_original")){
                rename($htaccess,".htaccess_original");
            }
            
            // create new htaccess_original file
            
            if(!file_exists($htaccess)){
                $f = fopen($htaccess,"w");
                fwrite($f,$script);
                fclose($f);
            }
            
            
        }
        
        
        
        
        // to add routes and the callback function to the routes
        function route($route,$function){
            if(is_string($route)){
                $this->routes[$route] = $function;
            }else if(is_array($route)){
                foreach ($route as $r){
                    $this->routes[$r] = $function;
                }
            }else{
                throw new Exception("Data passed as the first parameter for 'WebApp::route()' function must be a 'string' or 'array'");
            }
        }
        
        
        // to set 404 not found page
        function set_404_page(string $file_name){
            $this->error_404_page = $file_name;
        }
        
        
        
        // to recieve the "DB" object
        
        function db(DB $db){$this->db_obj = $db;}
        
        
        // to run the app
        function run(){
        
            $uri = "";
            
            // removing extra slash
            
            $full_url = explode("?",$_SERVER["REQUEST_URI"]);
            $uri_split = explode("/",$full_url[0]);
            
            foreach ($uri_split as $u){
                if(!empty($u)){ $uri = $uri."/".$u; }
            }
            
            
            $_uri = (empty($uri)) ? "/":$uri;
            
            
            // the uri match with any of the route
            
            if($this->routes[$_uri]){
                
                // using this class you can get "post/get" params and "resuest_method"
                $request = new class($_SERVER){
                    function __construct($srvr){
                        $this->method = strtolower($srvr["REQUEST_METHOD"]);
                    }
                    public function param(string $key){ return $_REQUEST[$key]; }
                };
                
                $route_func = $this->routes[$_uri];
                $data = $route_func($request,$this->db_obj);
                
                if(is_string($data)){
                    echo $data;
                }else if(is_array($data)){
                    $file_name = $data["file_name"];
                    $args = $data["args"];
                    $escape = $data["escape"];
                    $this->inflate($file_name,$args,$escape);
                }else{
                    echo "Your function hasn't returned any data to display!";
                }
            
            }else{
                
                // now will check if any variable request is found in any routes
                
                $matched_route = "";
                $route_variables = [];
                $route_variables_values = [];
                $u_split = explode("/",$_uri);
                
                // checking that if any matching route in the list
                foreach($this->routes as $r=>$f){
                    $r_split = explode("/",$r);
                    
                    if(($r_split[1]==$u_split[1]) && (count($u_split)==count($r_split))){
                        $matched_route = $r;
                        
                        // extracting the variable names
                        foreach ($r_split as $key_index=>$key_route){
                            $var_pattern = "/(?<=[<])\w+(?=[>])/";
                            if(preg_match($var_pattern,$key_route,$match)){
                                $route_variables[$key_index]  = $match[0];
                            }
                        }
                        
                        // assigning the data to the variable names given
                        foreach ($route_variables as $i=>$k){
                            $route_variables_values[$k] = $u_split[$i];
                        }
                        
                        break;
                    }
                }
                
                
                
                if($matched_route){
                    
                    // using this class you can get "post/get" params and "resuest_method"
                    $request = new class($_SERVER){
                        function __construct($srvr){
                            $this->method = strtolower($srvr["REQUEST_METHOD"]);
                        }
                        public function param(string $key){ return $_REQUEST[$key]; }
                    };
                    
                    $r_vv = $route_variables_values;
                    
                    $route_func = $this->routes[$matched_route];
                    $data = $route_func($request,$r_vv,$this->db_obj);
                    
                    if(is_string($data)){
                        echo $data;
                    }else if(is_array($data)){
                        $file_name = $data["file_name"];
                        $args = $data["args"];
                        $escape = $data["escape"];
                        
                        $this->inflate($file_name,$args,$escape);
                    }else{
                        echo "Your function hasn't returned any data to display!";
                    }
                
                }else{
                    if($this->error_404_page){
                        $args = [
                                    "title" => "404",
                                    "subtitle" => "Not Found!"
                                ];
                        $this->inflate($this->error_404_page,$args);
                    }else{
                        echo "
                            <title> 404 - Not Found! </title>
                            <meta name='viewport' content='width=device-width' />
                            <h1 style='color:red;'> Not Found! </h1>
                        ";
                    }
                }
            }
            
        }
    
        
    }
    

    
    
    
    
    // to pass the page name and any variables to the page
        
    function template(string $file_name,array $args=[],bool $escape=false){
        return [
                    "file_name" => $file_name,
                    "args" => $args,
                    "escape" => $escape
               ];
    }
    
    
    
    // to get and set $_SESSION variable
    
    function session(string $key,string $value=""){
        session_start();
        if(!empty($key) && !empty($value)){
            $_SESSION[$key] = $value;
        }else if(!empty($key) && empty($value)){
            return $_SESSION[$key];
        }
    }
    
    
    // ro unset session variable
    
    function unset_session(string $key){
        unset($_SESSION[$key]);
    }
    
    
    // to redirect headers
    
    function redirect(string $destination,array $params=[]){
        $qs = "";
        foreach ($params as $k=>$v){
            $and = ($v==end($params)) ? "":"&";
            $qs = $qs."$k=$v".$and;
        }
        $qs = ($qs) ? "?$qs":"";
        header("location:$destination".$qs);
    }
    
    
    
    
?>