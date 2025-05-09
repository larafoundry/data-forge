<?php

declare(strict_types=1);

namespace Tests\Unit\AsDto;

use Tests\Unit\AsDto\Objects\NestedDto;
use Tests\Unit\AsDto\Objects\BasicDto;
use PHPUnit\Framework\TestCase;

class NestedDtoTest extends TestCase
{
    public function testCanCreateNestedDto(): void
    {
        // First, create a BasicDto instance
        $personData = [
            'name' => 'John Doe',
            'age' => 30,
            'email' => 'john@example.com',
        ];
        
        $person = BasicDto::fromArray($personData);
        
        // Now create a NestedDto using the BasicDto
        $data = [
            'title' => 'Manager',
            'person' => $person,
        ];
        
        $nestedDto = new NestedDto(
            title: $data['title'],
            person: $data['person']
        );
        
        $this->assertInstanceOf(NestedDto::class, $nestedDto);
        $this->assertEquals('Manager', $nestedDto->title);
        $this->assertInstanceOf(BasicDto::class, $nestedDto->person);
        $this->assertEquals('John Doe', $nestedDto->person->name);
        $this->assertEquals(30, $nestedDto->person->age);
        $this->assertEquals('john@example.com', $nestedDto->person->email);
    }
}
