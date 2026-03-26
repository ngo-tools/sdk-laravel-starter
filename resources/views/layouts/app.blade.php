<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
</head>
<body class="bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-100">
    @yield('content')

    {{-- NGO.Tools Bridge (inline to avoid PNA/CORS issues in dev tunnels) --}}
    <script>
    (function(global){'use strict';var _state={accessToken:null,tenantId:null,locale:'de',theme:'light',primaryColor:'#6366f1',apiBaseUrl:'',user:null,context:{entityType:null,entityId:null}};var _callbacks=[];var _initialized=false;function isInIframe(){try{return window.self!==window.top}catch(e){return true}}function applyTheme(theme){document.documentElement.classList.toggle('dark',theme==='dark')}function handleMessage(event){var data=event.data||{};var type=data.type;var payload=data.payload;if(type==='init'&&payload){_state={accessToken:payload.access_token,tenantId:payload.tenant_id,locale:payload.locale||'de',theme:payload.theme||'light',primaryColor:payload.primary_color||'#6366f1',apiBaseUrl:payload.api_base_url,user:payload.user,context:{entityType:payload.context?payload.context.entity_type:null,entityId:payload.context?payload.context.entity_id:null}};_initialized=true;applyTheme(_state.theme);_callbacks.forEach(function(cb){cb(_state)})}if(type==='theme-changed'&&payload){_state.theme=payload.theme;applyTheme(payload.theme)}}window.addEventListener('message',handleMessage);if(isInIframe()){var s=document.createElement('script');s.src='https://cdn.jsdelivr.net/npm/@iframe-resizer/child@5.5.8';document.head.appendChild(s)}global.NGOTools={onInit:function(callback){_callbacks.push(callback);if(_initialized)callback(_state)},getState:function(){return JSON.parse(JSON.stringify(_state))},sendNavigate:function(route){window.parent.postMessage({type:'navigate',payload:{route:route}},'*')},applyTheme:applyTheme,enableDevMode:function(options){if(isInIframe())return;var defaults={access_token:'dev-token',tenant_id:1,locale:'de',theme:'light',primary_color:'#6366f1',api_base_url:'http://localhost/api/v2',user:{id:1,name:'Dev User'},context:{entity_type:null,entity_id:null}};var overrides=(options&&options.payload)||{};var payload={};for(var k in defaults)payload[k]=defaults[k];for(var k in overrides)payload[k]=overrides[k];setTimeout(function(){window.dispatchEvent(new MessageEvent('message',{data:{type:'init',payload:payload}}))},0)}}})(typeof globalThis!=='undefined'?globalThis:window);
    </script>
    <script>
        NGOTools.onInit(function(state) {
            NGOTools.applyTheme(state.theme);
            document.dispatchEvent(new CustomEvent('ngotools:init', { detail: state }));
        });
    </script>
    @stack('scripts')
</body>
</html>
