<x-filament-panels::page>
 <div class="space-y-6">
  <div class="flex flex-wrap gap-4"><a href="{{ \App\Filament\Pages\Operations::getUrl() }}">Dashboard</a>@if(auth()->user()->hasPermission('admin.audit_logs.view'))<a href="{{ \App\Filament\Pages\Operations::getUrl(['section'=>'audit']) }}">Audit</a>@endif<a href="{{ route('admin.assigned-profiles.index') }}">Trang nghiệp vụ Blade</a></div>
  @isset($content['dashboard'])
   @php($resourceLabels=['tutor_profiles'=>'Hồ sơ gia sư','reports'=>'Báo cáo','reviews'=>'Đánh giá','notification_outbox'=>'Thông báo đang xử lý'])
   @php($countLabels=['mine'=>'Được giao cho tôi','unassigned'=>'Chưa phân công','submitted'=>'Chờ duyệt','changes_requested'=>'Cần chỉnh sửa','approved'=>'Đã duyệt','rejected'=>'Đã từ chối','suspended'=>'Đình chỉ','open'=>'Đang mở','resolved'=>'Đã giải quyết','dismissed'=>'Đã bác bỏ','visible'=>'Công khai','hidden'=>'Đã ẩn','pending'=>'Đang chờ','failed'=>'Lỗi xử lý'])
   @forelse($content['dashboard']['resources'] as $name=>$counts)<x-filament::section :heading="$resourceLabels[$name] ?? $name">@foreach($counts as $state=>$count)<h3>{{ $countLabels[$state] ?? $state }}</h3>@if(is_array($count))@foreach($count as $status=>$total)<p>{{ $countLabels[$status] ?? $status }}: <strong>{{ $total }}</strong></p>@endforeach @else<p>{{ $count }}</p>@endif @endforeach</x-filament::section>@empty<p>Chưa có tài nguyên được cấp quyền.</p>@endforelse
  @endisset
  @isset($content['profile'])
   @include('admin.partials.profile', ['item'=>$content['profile'],'livewire'=>true])
  @endisset
  @isset($content['report'])
   <x-filament::section heading="Báo cáo"><x-admin-data :data="$content['report']"/></x-filament::section>
   @if($content['evidence'])<x-filament::section heading="Evidence trong phạm vi cho phép"><x-admin-data :data="$content['evidence']"/></x-filament::section>@else<p>Evidence không còn khả dụng khi báo cáo đã đóng.</p>@endif
   @if($content['report']['status']==='open'&&auth()->user()->hasPermission('admin.reports.decide'))<div class="flex gap-4"><x-filament::button wire:click="operate('resolve')" wire:loading.attr="disabled">Giải quyết</x-filament::button><x-filament::button color="gray" wire:click="operate('dismiss')" wire:loading.attr="disabled">Bác bỏ</x-filament::button></div>@endif
  @endisset
  @isset($content['review'])
   <x-filament::section heading="Đánh giá"><p>{{ $content['review']['rating'] }}/5 · {{ $content['review']['visibility'] }}</p><p>{{ $content['review']['comment'] }}</p><x-filament::button wire:click="operate('{{ $content['review']['visibility']==='visible'?'hide':'restore' }}')" wire:loading.attr="disabled">{{ $content['review']['visibility']==='visible'?'Ẩn đánh giá':'Khôi phục hiển thị' }}</x-filament::button></x-filament::section>
  @endisset
  @isset($content['assignment'])
   <x-filament::section heading="Phân công"><p>#{{ $content['assignment']['id'] }} · {{ $content['assignment']['status'] }} · v{{ $content['assignment']['assignment_version'] }}</p><label for="assignee">ID quản trị nhận việc (để trống để thu hồi)</label><x-filament::input.wrapper><x-filament::input id="assignee" type="number" min="1" wire:model="assignee"/></x-filament::input.wrapper><x-filament::button wire:click="operate('assign')" wire:loading.attr="disabled">Cập nhật phân công</x-filament::button></x-filament::section>
  @endisset
  @isset($content['audit'])
   <x-filament::section heading="Audit chỉ đọc"><div class="overflow-auto"><table class="w-full"><thead><tr><th>ID</th><th>Hành động</th><th>Đối tượng</th><th>Trước / Sau</th><th>Lý do</th></tr></thead><tbody>@foreach($content['audit']['data'] as $row)<tr><td>{{ $row['id'] }}</td><td>{{ $row['action'] }}</td><td>{{ $row['resource_type'] }} #{{ $row['resource_id'] }}</td><td><x-admin-data :data="['before'=>$row['before'],'after'=>$row['after']]"/></td><td>{{ $row['reason'] }}</td></tr>@endforeach</tbody></table></div><p>Trang {{ $content['audit']['meta']['current_page'] }} · {{ $content['audit']['meta']['total'] }} bản ghi</p>@if($content['audit']['meta']['current_page']>1)<a href="{{ \App\Filament\Pages\Operations::getUrl(['section'=>'audit','page'=>$content['audit']['meta']['current_page']-1]) }}">Trước</a>@endif @if($content['audit']['meta']['current_page']*20<$content['audit']['meta']['total'])<a href="{{ \App\Filament\Pages\Operations::getUrl(['section'=>'audit','page'=>$content['audit']['meta']['current_page']+1]) }}">Sau</a>@endif</x-filament::section>
  @endisset
  @if($section!=='dashboard'&&$section!=='audit')<div><label for="operation-reason">Lý do / Kết quả xử lý</label><x-filament::input.wrapper><x-filament::input id="operation-reason" wire:model="reason" maxlength="2000"/></x-filament::input.wrapper>@error('reason')<p role="alert">{{ $message }}</p>@enderror @error('resolution_note')<p role="alert">{{ $message }}</p>@enderror<x-filament::button color="gray" wire:click="refreshRecord" wire:loading.attr="disabled">Tải phiên bản mới nhất</x-filament::button></div>@endif
 </div>
</x-filament-panels::page>
