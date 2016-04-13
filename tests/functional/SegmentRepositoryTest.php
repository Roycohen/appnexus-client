<?php

namespace Test\functional;

use Audiens\AppnexusClient\entity\Segment;
use Prophecy\Argument;
use Test\FunctionalTestCase;

/**
 * Class SegmentRepositoryTest
 */
class SegmentRepositoryTest extends FunctionalTestCase
{

    /**
     * @test
     */
    public function add_will_create_a_new_segment_and_return_a_repository_response()
    {
        $repository = $this->getSegmentRepository();

        $segment = new Segment();
        $segment->setName('Test segment'.uniqid());
        $segment->setCategory('Test category'.uniqid());
        $segment->setMemberId(getenv('MEMBER_ID'));
        $segment->setActive(true);

        $repositoryResponse = $repository->add($segment);

        $this->assertTrue($repositoryResponse->isSuccessful(), $repositoryResponse->getError()->getError());
        $this->assertNotNull($segment->getId());


    }

    /**
     * @test
     */
    public function find_one_by_id_will_retrive_a_newly_created_segment()
    {

        $repository = $this->getSegmentRepository();

        $segment = new Segment();

        $segment->setName('Test segment '.uniqid());
        $segment->setCategory('a-test-category');
        $segment->setDescription('a test description 123456');
        $segment->setCode(rand(0, 999) * rand(0, 999));
        $segment->setPrice(10.11);
        $segment->setMemberId(getenv('MEMBER_ID'));
        $segment->setActive(true);

        $repository->add($segment);

        $segmentFound = $repository->findOneById($segment->getMemberId(), $segment->getId());

        $this->assertNotNull($segmentFound, 'find one by id segment not found');

        $this->assertEquals($segment->getId(), $segmentFound->getId());
        $this->assertEquals($segment->getDescription(), $segmentFound->getDescription());


    }

    /**
     * @test
     */
    public function find_all_will_return_multiple_segments()
    {

        $repository = $this->getSegmentRepository();

        $segment = new Segment();

        $segment->setName('Test segment '.uniqid());
        $segment->setCategory('a-test-category');
        $segment->setDescription('a test description 123456');
        $segment->setCode(rand(0, 999) * rand(0, 999));
        $segment->setPrice(10.11);
        $segment->setMemberId(getenv('MEMBER_ID'));
        $segment->setActive(true);

        $repository->add($segment);

        $segments = $repository->findAll(getenv('MEMBER_ID'), 0, 2);

        $this->assertCount(2, $segments);

        foreach ($segments as $segment) {
            $this->assertInstanceOf(Segment::class, $segment);
        }

    }

    /**
     * @test
     */
    public function delete_will_remove_a_segment()
    {
        $repository = $this->getSegmentRepository();

        $segment = new Segment();

        $segment->setName('Test segment '.uniqid());
        $segment->setCategory('a-test-category');
        $segment->setDescription('a test description 123456');
        $segment->setCode(rand(0, 999) * rand(0, 999));
        $segment->setPrice(10.11);
        $segment->setMemberId(getenv('MEMBER_ID'));
        $segment->setActive(true);

        $repositoryResponse = $repository->add($segment);

        $this->assertTrue($repositoryResponse->isSuccessful(), $repositoryResponse->getError()->getError());

        $repositoryResponse = $repository->remove($segment->getMemberId(), $segment->getId());

        $this->assertTrue($repositoryResponse->isSuccessful(), $repositoryResponse->getError()->getError());


    }

    /**
     * @test
     */
    public function update_will_edit_an_existing_segment()
    {

        $repository = $this->getSegmentRepository();

        $segment = new Segment();

        $segment->setName('Test segment '.uniqid());
        $segment->setCategory('a-test-category');
        $segment->setDescription('a test description 123456');
        $segment->setCode(rand(0, 999) * rand(0, 999));
        $segment->setPrice(10.11);
        $segment->setMemberId(getenv('MEMBER_ID'));
        $segment->setActive(true);

        $repositoryResponse = $repository->add($segment);
        $this->assertTrue($repositoryResponse->isSuccessful(), $repositoryResponse->getError()->getError());

        $segment->setPrice(12.11);

        $repositoryResponse = $repository->update($segment);
        $this->assertTrue($repositoryResponse->isSuccessful(), $repositoryResponse->getError()->getError());

        $this->assertEquals(12.11, $segment->getPrice());

    }

}
