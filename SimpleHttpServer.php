<?php
class SimpleHttpServer
{
    private $socket;

    public function __construct(private string $host = '0.0.0.0', private int $port = 8000) {} // Устанавливаем свойства класса

    public function listen(callable $requestHandler): void // Создаём метод который будет использоваться нами в дальнейшем для работы с классом
    {
        $this->createSocket(); // используем метод создание сокета
        
        echo "Server running on http://{$this->host}:{$this->port}\n"; //Выводим в консоль информацию о том что сервер запущен
        
        while ($conn = stream_socket_accept($this->socket, -1)) {// Ждём запрос
            $request = fread($conn, 8192); // Читаем 8КБ данных из запроса
            $requestInfo = $this->parseRequest($request); // Парсим запрос
            
            $response = $requestHandler($requestInfo); // Передаём в нашу callback функцию
            
            $this->sendResponse($conn, $response); // возвращаем ответ
            fclose($conn);
        }
    }


    private function parseRequest(string $request): array
    {
        $lines = explode("\r\n", $request);  // Разбиваем запрос по переносы строки
        $firstLine = explode(' ', $lines[0]); // Разбиваем первую строку по пробелу
        
        return [ // Возвращаем распаршенный запрос
            'method' => $firstLine[0] ?? 'GET', // Определяем метод, если он не указан, пишем GET
            'path' => $firstLine[1] ?? '/', // Определяем запрашиваемую локацию
            'headers' => $this->extractHeaders($lines), // Извлекаем заголовки
            'body' => $this->extractBody($lines), // Извлекаем тело запроса
            'raw' => $request // И добавляем сырой запрос
        ];
    }


    private function extractHeaders(array $lines): array
    {
        $headers = []; // Создаём массив заголовков
        foreach ($lines as $line) { // Перебираем строки
            if (strpos($line, ':') !== false) { // Если есть двоеточие
                [$name, $value] = explode(':', $line, 2); // Тогда сохраняем имя заголовка и значение
                $headers[trim($name)] = trim($value); // Добавляем в массив, обрезая пустые символы
            }
        }
        return $headers; // Возвращаем
    }

    private function extractBody(array $lines): string
    {
        $bodyStart = array_search('', $lines) + 1; // Ищем строку с которой начинается тело, по пустой строке
        return implode("\r\n", array_slice($lines, $bodyStart)); //Соеденяем всё тело как было и возвращаем
    }

    private function createSocket(): void // Метод для создания сокета
    {
        $this->socket = stream_socket_server(
            "tcp://{$this->host}:{$this->port}", // Указываем хост и порт для сокета 

            // Переменные для ошибок
            $errno, 
            $errstr
        );

        if (!$this->socket) {
            throw new RuntimeException("Failed to create socket: $errstr ($errno)"); // Выкидываем исключение в случае ошибки создания сокета
        }
    }

    private function sendResponse($conn, array|string $response): void // Метод для отправки ответ
    {
        if (is_array($response)) { // Если на вход передан массив то формируем заголовки
            $status = $response['status'] ?? 200; // Статус 200 - успех
            $headers = $response['headers'] ?? ['Content-Type' => 'text/plain']; // Массив заголовков
            $body = $response['body'] ?? ''; // Тело ответа
            
            $headerString = "HTTP/1.1 $status OK\r\n"; // Строка заголовка
            foreach ($headers as $name => $value) { // Перебираем массив заголовков и составляем строку
                $headerString .= "$name: $value\r\n";
            }
            
            fwrite($conn, $headerString . "\r\n" . $body); // В конце добавляем ещё перенос строки так чтобы получилась пустая строка и записываем в сокет
        } else {
            fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\n$response"); // Если передана строка возвращаем
        }
    }


}