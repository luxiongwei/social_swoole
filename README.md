## 环境依赖

- PHP 7.2
- Redis 4
- Swoole 4.4
- NPM 6.12.0

## 接口规范

项目遵循 JSON RPC 2.0 规范。

## 请求对象

- jsonrpc
    - 指定JSON-RPC协议版本的字符串，必须准确写为 "2.0"。
- method
    - 包含所要调用方法名称的字符串。
- params
    - 调用方法所需要的结构化参数值。
- id
    - 已建立客户端的唯一标识 id，值必须包含一个字符串、数值或 NULL 空值。
    
以 HTTP 协议为例子，传输信息如下：
```http
GET  HTTP/1.1
Host: 127.0.0.1:9503
Connection: keep-alive
Keep-Alive: timeout=120
Content-Type: application/json
cache-control: no-cache

{
    "jsonrpc":"2.0",
    "method":"ChatRadio.join",
    "params":{
        "user_id":7024480,
        "user_gender":1,
        "match_times":0,
        "match_score":0,
        "match_gender":0
    },
    "id":null
}
```    

## 成功响应对象

- jsonrpc
    - 指定 JSON-RPC 协议版本的字符串，必须准确写为 "2.0"。
- result
    - 成功时包含，返回调用方法的处理结果。
- id
    - 已建立客户端的唯一标识 id
    
## 失败响应对象

- jsonrpc
    - 指定 JSON-RPC 协议版本的字符串，必须准确写为 "2.0"。
- error
    - code
        - 使用数值表示该异常的错误类型。 必须为整数。
    - data
        - 包含关于错误附加信息的基本类型或结构化类型，可忽略。
    - message
        - 对该错误的简单描述字符串。
- id
    - 已建立客户端的唯一标识 id
    
错误代码与错误信息对应如下：

| code | message | meaning |
| :-----| ----: | :----: |
| -32600 | 无效请求 | 发送的 JSON 不是一个有效的请求对象 |
| -32601 | 找不到方法 | 该方法不存在或无效 |
| -32602 | 无效的参数 | 无效的方法参数 |
| -32700 | 语法解析错误 | 服务端接收到无效的 JSON。该错误发送于服务器尝试解析 JSON 文本 |
| -32800 | 没有匹配到合适的用户 | 在规定时间内没有匹配到合适的用户 |

## 支持的调用方法及其参数

- 方法：ChatRadio.join - 进入匹配池子进行匹配
    - 参数（params 字段）
        - user_id：用户 ID
        - user_gender：用户性别
        - match_times：用户匹配次数
        - match_score：用户匹配得分
        - match_gender：用户筛选性别，0 - 全部，1 - 男士，2 - 女士
        - user_avatar：匹配成功的用户头像
        - user_nickname：匹配成功的用户昵称

    - 响应（result 字段）
        - token：声网令牌
        - channel：声网频道
        - user_id：当前用户ID
        - app_id：声网APP ID
        - relation：自己与对方关系：0=>显示"认识ta"， 1=>显示"等待状态"， 2=>显示"聊天"， 3=>隐藏按钮
        - match_user_relation：对方与自己关系（同上）
        - match_user_info：匹配成功的用户信息，@see params
    
## 开启进程守护

首先安装 PM2，执行命令如下：
```
npm install pm2@latest -g
```

移动至项目根目录，执行命令如下：
```
pm2 start easyswoole --interpreter=php -- start produce
```

## 压力测试