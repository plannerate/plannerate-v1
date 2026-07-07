@props(['url'])
{{--
    Header dos e-mails do Plannerate.

    Sobrescreve o componente padrão do Laravel (que exibia o logo do framework
    sempre que config('app.name') resolvia para "Laravel"). Aqui a marca é fixa
    para não depender de APP_NAME/config cache no worker de filas.
--}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; color: #1f2937; font-size: 22px; font-weight: 700; letter-spacing: -0.4px; text-decoration: none;">
Plannerate
</a>
</td>
</tr>
