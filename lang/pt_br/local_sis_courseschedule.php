<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Portuguese (Brazil) language strings.
 *
 * @package    local_sis_courseschedule
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addslots'] = 'Adicionar mais intervalos';
$string['allowedhours'] = 'Horários de acesso permitidos';
$string['backtomycourses'] = 'Ir para Meus cursos';
$string['blockedtitle'] = 'Curso indisponível';
$string['courseaccessclosed'] = 'Não é possível acessar este curso no momento. {$a}';
$string['courseaccessclosedshort'] = 'Não é possível acessar este curso no momento.';
$string['currenttime'] = 'Horário atual no curso: {$a->day}, {$a->time} ({$a->timezone})';
$string['editschedule'] = 'Horários de acesso ao curso';
$string['enabled'] = 'Restringir o acesso aos horários programados';
$string['enabled_help'] = 'Quando ativado, o curso só pode ser acessado nos intervalos abaixo, no fuso horário do curso. Usuários com a permissão "Acessar o curso fora dos horários permitidos" não são restringidos. Matrículas, notas e progresso não são alterados.';
$string['endtime'] = 'Término (HH:MM)';
$string['errorendbeforestart'] = 'O término deve ser posterior ao início.';
$string['errorinvalidtime'] = 'Use o formato HH:MM (00:00 a 23:59; 24:00 é aceito apenas como término).';
$string['errorinvalidtimezone'] = 'Selecione um fuso horário válido.';
$string['errornointerval'] = 'A restrição está ativa, mas nenhum intervalo foi preenchido. Adicione pelo menos um intervalo semanal abaixo (dia, início e término), ou desmarque "Restringir o acesso aos horários programados" para deixar o curso livre.';
$string['erroroverlap'] = 'Este intervalo se sobrepõe a outro intervalo no mesmo dia.';
$string['errorpartialinterval'] = 'Preencha o início e o término, ou deixe ambos vazios.';
$string['intervalcrossmidnight'] = 'Um intervalo não pode atravessar a meia-noite. Para permitir, por exemplo, de segunda 22:00 a terça 02:00, cadastre segunda 22:00-24:00 e terça 00:00-02:00.';
$string['intervalsheader'] = 'Intervalos semanais';
$string['managescheduleblocked'] = 'Gerenciar os horários de acesso';
$string['managerbypassnotice'] = 'Você pode configurar estes horários, mas não tem a permissão "Acessar o curso fora dos horários permitidos". Enquanto a restrição estiver ativa, o conteúdo do curso permanece bloqueado para você fora dos horários permitidos; esta página de configuração continua disponível.';
$string['navigationlabel'] = 'Horários de acesso';
$string['pluginname'] = 'Horários de acesso ao curso';
$string['privacy:metadata'] = 'O plugin Horários de acesso ao curso armazena apenas a configuração do curso (intervalos semanais e fuso horário). Não armazena dados pessoais.';
$string['scheduleimpact'] = 'Fora dos horários permitidos, o curso, suas atividades e seus arquivos não podem ser abertos online, e respostas não podem ser enviadas nem sincronizadas. O salvamento automático e o envio de questionários após o fechamento podem ser negados. Conteúdo já baixado no aplicativo móvel continua utilizável offline, mas o que for enviado após o fechamento é recusado pelo servidor. As alterações valem a partir da próxima requisição.';
$string['scheduleline'] = '{$a->day}: {$a->intervals}';
$string['schedulenone'] = 'Nenhum horário está configurado para este curso.';
$string['scheduleotherdays'] = 'Nos demais dias o curso não está disponível.';
$string['schedulesaved'] = 'Os horários de acesso foram salvos. Horário atual: {$a}';
$string['schedulesummary'] = 'Horário salvo atualmente';
$string['scheduletimezone'] = 'Fuso horário: {$a}';
$string['sis_courseschedule:bypass'] = 'Acessar o curso fora dos horários permitidos';
$string['sis_courseschedule:manage'] = 'Gerenciar os horários de acesso ao curso';
$string['starttime'] = 'Início (HH:MM)';
$string['timezone'] = 'Fuso horário do curso';
$string['timezone_help'] = 'Os horários permitidos são avaliados neste fuso horário, independentemente do fuso de cada usuário.';
$string['weekday'] = 'Dia da semana';
