# Tận dụng test từ open source cho Data Forge

## Mục tiêu

Mục tiêu không phải là "tham khảo cho vui", mà là tìm xem có thể **bê test case từ các package DTO/hydrator open source về Data Forge** để tăng tốc phát triển hay không.

Kết luận ngắn:

- **Có thể tận dụng**, nhưng phần lớn nên bê theo kiểu **port lại scenario** chứ không nên copy nguyên test file.
- Với Data Forge, giá trị lớn nhất nằm ở:
  - ma trận input edge case
  - nested DTO / nested collection
  - mapped key và error path
  - enum / date / default / nullable behavior
- Rủi ro lớn nhất không phải là license, mà là **bê nhầm behavior không cùng contract**.

## Contract hiện tại của Data Forge

Data Forge đang theo boundary rất rõ:

- `raw input -> strict preflight -> normalize to canonical keys -> validate raw/projected shape -> hydrate/cast -> validate typed data -> instantiate DTO`
- `#[MapKey]` là mapping explicit giữa raw key và canonical DTO key
- DTO không strict thì bỏ qua unknown raw key
- DTO strict thì reject unknown raw key và phải giữ nguyên raw path
- không đoán alias
- không broad auto-cast scalar từ string
- nested validation/error path phải rõ
- nested hydration không được phá boundary giữa input key và canonical key

Điều này có nghĩa là một test từ open source chỉ đáng bê nếu nó kiểm tra:

- shape/data boundary
- nested error path
- nullable/default/enum/date behavior
- mapped key behavior
- collection hydration behavior

và **không** kéo theo:

- alias ngầm
- global key formatter
- permissive type system
- broad scalar coercion
- lazy validation
- framework integration vượt scope package

## Vấn đề license

Cả 5 reference đã xem đều đang để **MIT** trong `composer.json` hoặc file license:

- `references/cuyz-valinor/composer.json`
- `references/eventsaucephp-object-hydrator/composer.json`
- `references/spatie-data-transfer-object/composer.json`
- `references/spatie-laravel-data/composer.json`
- `references/wendelladriel-laravel-validated-dto/composer.json`

Về mặt thực tế:

- **MIT không phải trở ngại lớn** để học hoặc port lại scenario.
- Nếu copy code gần như nguyên văn, nên giữ provenance và phần notice phù hợp.
- Cách an toàn nhất vẫn là:
  - lấy **ý tưởng test**
  - đổi fixture sang API của Data Forge
  - rewrite assertion theo contract của Data Forge

Nói ngắn gọn: **license ổn, behavior mới là chỗ cần dè chừng**.

## Đánh giá từng reference

### 1. `cuyz/valinor`

Quy mô test rất lớn: khoảng **351** file dưới `tests/`.

Những file đại diện đã xem:

- `references/cuyz-valinor/tests/Integration/Mapping/Other/StrictMappingTest.php`
- `references/cuyz-valinor/tests/Integration/Mapping/Other/ScalarValueCastingMappingTest.php`
- `references/cuyz-valinor/tests/Integration/Mapping/Other/SuperfluousKeysMappingTest.php`

Điểm mạnh:

- rất giàu edge case cho:
  - missing field
  - enum/date/list/array edge cases
  - nested path reporting
  - non-sequential list và shaped array
  - multi-error assertion

Thứ có thể bê:

- **Medium**:
  - missing field ở nested object
  - nested error path assertion
  - enum/date failure matrix
  - list/array shape edge case
- **High**:
  - union/permissive type matrix
  - shaped array / `array{}` / `list<>` style cases
  - message format assertions của riêng Valinor

Thứ không nên bê trực tiếp:

- `allowScalarValueCasting()` trong `ScalarValueCastingMappingTest`
- permissive type / `mixed` / `object` behavior
- type language rất riêng của Valinor
- mapper builder settings như `allowSuperfluousKeys()` và các strict mode riêng

Kết luận:

- **Không phải nguồn để copy test trực tiếp**.
- Rất tốt để **đào edge-case matrix** và chuyển thành test Data Forge theo contract riêng.

### 2. `eventsauce/object-hydrator`

Quy mô test nhỏ hơn nhiều, chủ yếu nằm ngay trong `src/`: khoảng **8** file test.

Những file đại diện đã xem:

- `references/eventsaucephp-object-hydrator/src/ObjectHydrationTestCase.php`
- `references/eventsaucephp-object-hydrator/src/ObjectMapperUsingReflectionHydrationTest.php`

Điểm mạnh:

- rất gần bài toán hydration object:
  - nested object
  - mapped property
  - nested mapped key
  - list of objects
  - enum/date/default/nullable
  - missing nested field propagation

Thứ có thể bê:

- **Low to Medium**:
  - missing required nested field
  - nullable/default behavior
  - enum/date hydration
  - list of nested objects
  - nested property mapping
  - nested error propagation

Thứ cần tránh:

- custom caster stacks
- key formatter tự động như snake_case conversion
- polymorphic type mapping
- philosophy "hydrate first" không có validation boundary rõ như Data Forge

Kết luận:

- Đây là **nguồn tốt nhất để bê test hydration-level scenario**.
- Không nên copy nguyên test harness, nhưng nhiều test có thể rewrite khá thẳng vào:
  - `tests/Unit/Hydration`
  - `tests/Feature/DtoPipeline`

### 3. `spatie/data-transfer-object`

Quy mô vừa: khoảng **35** file test.

Những file đại diện đã xem:

- `references/spatie-data-transfer-object/tests/StrictDtoTest.php`
- `references/spatie-data-transfer-object/tests/MapFromTest.php`
- `references/spatie-data-transfer-object/tests/ValidationTest.php`
- `references/spatie-data-transfer-object/tests/ScalarPropertyTest.php`

Điểm mạnh:

- test khá cô đọng và gần với DTO use case:
  - strict vs non-strict
  - mapped input
  - default values
  - nested mapping
  - numeric key mapping

Thứ có thể bê:

- **Low**:
  - strict vs non-strict unknown key behavior
  - mapped key với default value
  - nested mapped key concept
- **Medium**:
  - numeric key mapping
  - nested DTO mapping
- **High**:
  - caster-specific tests
  - validation attribute behavior riêng của package này

Điểm lệch cần lưu ý:

- `MapFrom('address.city')` và numeric index mapping rất tiện, nhưng khi port sang Data Forge phải giữ đúng boundary `raw key -> canonical key`, không mở đường cho alias ngầm.
- package này không bám chặt raw-vs-canonical boundary như Data Forge.
- nhiều test assume constructor/hydration model riêng của Spatie DTO.

Kết luận:

- Đây là **nguồn tốt để bê test cấp API DTO**.
- Nếu phải chọn một bộ nhỏ để mang về nhanh, đây là một trong hai nguồn nên làm sớm.

### 4. `spatie/laravel-data`

Quy mô rất lớn: khoảng **206** file test.

Những file đại diện đã xem:

- `references/spatie-laravel-data/tests/MappingTest.php`
- `references/spatie-laravel-data/tests/Casts/BuiltinTypeCastTest.php`
- `references/spatie-laravel-data/tests/ValidationTest.php`

Điểm mạnh:

- coverage cực rộng cho:
  - nested validation
  - collection validation
  - message/attribute mapping
  - nested payload path
  - manual rules / additional rules
  - validation messages cho nested collections

Đây là kho scenario rất giàu, đặc biệt ở `ValidationTest.php`.

Thứ có thể bê:

- **Medium**:
  - nested validation path
  - nested collection validation
  - message mapping cho nested data
  - mapped input key ở nested object/collection
  - required/nullable/optional matrix
- **High**:
  - request/model/container/livewire/testbench cases
  - custom validation dependency injection
  - database constraint rules
  - TypeScript / resource / transformation / response behavior

Trở ngại lớn:

- package này cho phép nhiều kiểu mapping/casting rất rộng
- `BuiltinTypeCastTest` cho thấy nó cast rất permissive:
  - `'true' -> bool`
  - `'42' -> int`
  - `42 -> '42'`
  - object -> array
- điều đó **không hợp** với nguyên tắc "no broad scalar auto-casting" của Data Forge
- nhiều test gắn chặt với Laravel runtime hơn là boundary DTO thuần

Kết luận:

- **Không phù hợp để bê nguyên test suite**.
- Rất đáng để **khai thác scenario validation khó**, nhất là nested collections, nested messages, mapped paths.

### 5. `wendelladriel/laravel-validated-dto`

Quy mô trung bình: khoảng **67** file test.

Những file đại diện đã xem:

- `references/wendelladriel-laravel-validated-dto/tests/Unit/ValidatedDTOTest.php`
- `references/wendelladriel-laravel-validated-dto/tests/Unit/IntegerCastTest.php`
- `references/wendelladriel-laravel-validated-dto/tests/Unit/LazyValidationTest.php`

Điểm mạnh:

- có các case khá thực dụng cho:
  - nested DTO
  - nested collection
  - enum/date
  - nullable/default
  - map-before-validation / map-before-export

Thứ có thể bê:

- **Medium**:
  - nested DTO / nested collection payload shape
  - enum/date materialization
  - nullable/default cases
- **High**:
  - map-before-validation
  - map-before-export
  - request/model/command/file upload integration
  - lazy validation
  - explicit caster tests

Điểm lệch lớn:

- package này cho phép lazy validation, trong khi Data Forge cần fail sớm ở boundary
- explicit cast classes là first-class behavior ở đây
- mapping trước validation có thể đẩy Data Forge lệch khỏi raw-input boundary hiện tại

Kết luận:

- Dùng được như **nguồn scenario phụ**, không nên coi là nguồn chính để bê test.

## Xếp hạng khả năng bê test về Data Forge

Nếu mục tiêu là "bê được test case về dùng nhanh nhất", thứ tự ưu tiên nên là:

1. `eventsauce/object-hydrator`
2. `spatie/data-transfer-object`
3. `spatie/laravel-data`
4. `cuyz/valinor`
5. `wendelladriel/laravel-validated-dto`

Ý nghĩa của thứ tự này:

- **EventSauce** gần bài toán hydration nhất, ít noise framework
- **Spatie DTO** có test nhỏ, gọn, dễ rewrite sang `AsDto` / `AsStrictInputDto`
- **Laravel Data** không dễ bê nguyên xi, nhưng là mỏ vàng cho nested validation scenario
- **Valinor** rất giàu edge cases nhưng chi phí chuyển hóa cao
- **Validated DTO** có nhiều behavior trái triết lý Data Forge hơn mức có lợi khi port

## Nên bê gì trước

Nếu bắt đầu ngay, nên bê các nhóm sau trước:

### Nhóm 1: bê gần như ngay được

- strict vs non-strict unknown input
- mapped key với default value
- nested mapped key path
- nullable/default behavior
- enum/date hydration
- nested DTO collection hydration
- missing nested required field

Các nhóm này nên đổ vào:

- `tests/Feature/AsDto`
- `tests/Feature/DtoPipeline`
- `tests/Unit/Hydration`
- `tests/Unit/Input`

### Nhóm 2: bê theo scenario, không bê code

- nested validation messages
- nested collection wildcard path
- deep nested collection rule path
- collection item validation propagation
- raw input key vs canonical error key mapping

Các nhóm này phù hợp với:

- `tests/Unit/Validation`
- `tests/Unit/Input/ErrorKeyMapperTest.php`
- `tests/Feature/DtoPipeline`

### Nhóm 3: chỉ nên khai thác edge-case matrix

- union / enum weird inputs
- list shape / non-sequential list
- deep nested failure path
- boundary message truncation / formatting

Những thứ này hợp để biến thành:

- test bug-poc
- regression test
- focused unit test nhỏ

## Không nên bê

Đây là các nhóm nếu bê vào sẽ dễ làm Data Forge trượt contract:

- broad scalar auto-casting từ string
- alias hoặc key formatter implicit
- map-before-validation làm mờ raw input boundary
- lazy validation
- framework integration test kiểu Request / Model / Command / Livewire / Testbench
- TypeScript/resource/response serialization
- permissive `mixed` / `object` / union resolver vượt scope hiện tại

## Cách tận dụng hiệu quả nhất

Thay vì copy nguyên test file, nên dùng workflow này:

1. Chọn **scenario**, không chọn package.
2. Rewrite fixture theo API Data Forge.
3. Assert theo contract của Data Forge:
   - input dùng raw key
   - DTO/property dùng canonical key
   - strict error dùng raw path
   - validation error mapping phản ánh external input key
4. Nếu chỉ mượn ý tưởng, không cần giữ nguyên cấu trúc test gốc.
5. Nếu copy một đoạn code đáng kể, ghi provenance trong commit hoặc note nội bộ.

## Kế hoạch đề xuất

### Đợt 1: port nhanh, lợi nhuận cao

Nguồn:

- `eventsauce/object-hydrator`
- `spatie/data-transfer-object`

Mục tiêu:

- thêm regression coverage cho hydration, defaults, nested mapping, strict unknown keys

### Đợt 2: đào sâu nested validation

Nguồn:

- `spatie/laravel-data`

Mục tiêu:

- bổ sung matrix cho nested DTO, nested collection, messages, dotted path, wildcard path

### Đợt 3: săn edge cases khó

Nguồn:

- `cuyz/valinor`

Mục tiêu:

- thêm regression test nhỏ cho các case khó mà hiện tại Data Forge có nguy cơ vỡ

## Kết luận cuối

**Có thể bê test case từ open source về cho Data Forge, nhưng nên bê theo kiểu rewrite scenario, không nên copy suite.**

Nếu chỉ chọn các nguồn thật sự giúp tăng tốc:

- dùng **EventSauce** cho hydration scenarios
- dùng **Spatie DTO** cho strict/mapping/default DTO scenarios
- dùng **Spatie Laravel Data** như kho scenario nested validation nâng cao

Phần còn lại chỉ nên dùng như nguồn ý tưởng edge cases, không nên coi là bộ test có thể di chuyển trực tiếp.
