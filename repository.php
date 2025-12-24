<?php
declare(strict_types=1);

require_once __DIR__ . '/models.php';

class DrivingExperienceRepository {
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function save(DrivingExperience $trip): int {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO trips (trip_date, departure_time, arrival_time, mileage_km,
                                  weather_id, time_of_day_id, surface_condition_id,
                                  road_condition_id, driver_health_id, latitude, longitude)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $trip->getTripDate(),
                $trip->getDepartureTime(),
                $trip->getArrivalTime(),
                $trip->getMileageKm(),
                $trip->getWeatherId(),
                $trip->getTimeOfDayId(),
                $trip->getSurfaceConditionId(),
                $trip->getRoadConditionId(),
                $trip->getDriverHealthId(),
                $trip->getLatitude(),
                $trip->getLongitude(),
            ]);
            
            $tripId = (int)$this->pdo->lastInsertId();
            $trip->setId($tripId);
            
            if (!empty($trip->getExternalFactors())) {
                $stmt = $this->pdo->prepare("INSERT INTO trip_external_factor (trip_id, factor_id) VALUES (?, ?)");
                foreach ($trip->getExternalFactors() as $factorId) {
                    $stmt->execute([$tripId, (int)$factorId]);
                }
            }
            
            $this->pdo->commit();
            return $tripId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function update(DrivingExperience $trip): void {
        if (!$trip->getId()) {
            throw new InvalidArgumentException("Cannot update trip without ID");
        }
        
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE trips SET trip_date=?, departure_time=?, arrival_time=?, mileage_km=?,
                                weather_id=?, time_of_day_id=?, surface_condition_id=?,
                                road_condition_id=?, driver_health_id=?, latitude=?, longitude=?
                WHERE id=?
            ");
            
            $stmt->execute([
                $trip->getTripDate(),
                $trip->getDepartureTime(),
                $trip->getArrivalTime(),
                $trip->getMileageKm(),
                $trip->getWeatherId(),
                $trip->getTimeOfDayId(),
                $trip->getSurfaceConditionId(),
                $trip->getRoadConditionId(),
                $trip->getDriverHealthId(),
                $trip->getLatitude(),
                $trip->getLongitude(),
                $trip->getId()
            ]);
            
            $this->pdo->exec("DELETE FROM trip_external_factor WHERE trip_id=" . $trip->getId());
            if (!empty($trip->getExternalFactors())) {
                $stmt = $this->pdo->prepare("INSERT INTO trip_external_factor (trip_id, factor_id) VALUES (?, ?)");
                foreach ($trip->getExternalFactors() as $factorId) {
                    $stmt->execute([$trip->getId(), (int)$factorId]);
                }
            }
            
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function delete(int $id): void {
        $stmt = $this->pdo->prepare("DELETE FROM trips WHERE id=?");
        $stmt->execute([$id]);
    }
    
    public function findAll(): array {
        $rows = $this->pdo->query("
            SELECT
                t.id, t.trip_date, t.departure_time, t.arrival_time, t.mileage_km,
                t.weather_id, t.time_of_day_id, t.surface_condition_id,
                t.road_condition_id, t.driver_health_id, t.latitude, t.longitude,
                w.name AS weather, tod.name AS time_of_day, sc.name AS surface, rc.name AS road,
                dh.name AS driver_health,
                CASE
                    WHEN t.arrival_time >= t.departure_time
                        THEN TIME_TO_SEC(TIMEDIFF(t.arrival_time, t.departure_time))
                    ELSE TIME_TO_SEC(TIMEDIFF(ADDTIME(t.arrival_time,'24:00:00'), t.departure_time))
                END AS duration_seconds
            FROM trips t
            JOIN weather w       ON w.id = t.weather_id
            JOIN time_of_day tod ON tod.id = t.time_of_day_id
            JOIN surface_cond sc ON sc.id = t.surface_condition_id
            JOIN road_cond rc    ON rc.id = t.road_condition_id
            JOIN driver_health dh ON dh.id = t.driver_health_id
            ORDER BY t.trip_date DESC, t.id DESC
        ")->fetchAll();
        
        $ids = array_column($rows, 'id');
        $factors = [];
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("
                SELECT tef.trip_id, ef.name
                FROM trip_external_factor tef
                JOIN external_factor ef ON ef.id = tef.factor_id
                WHERE tef.trip_id IN ($in)
            ");
            $stmt->execute($ids);
            foreach ($stmt as $r) {
                $factors[$r['trip_id']][] = $r['name'];
            }
        }
        
        $trips = [];
        foreach ($rows as $row) {
            $row['external_factors'] = $factors[$row['id']] ?? [];
            $trips[] = DrivingExperience::fromDatabaseRow($row);
        }
        
        return $trips;
    }
    
    public function findById(int $id): ?DrivingExperience {
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.trip_date, t.departure_time, t.arrival_time, t.mileage_km,
                   t.weather_id, t.time_of_day_id, t.surface_condition_id,
                   t.road_condition_id, t.driver_health_id, t.latitude, t.longitude
            FROM trips t WHERE t.id=?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        
        if (!$row) return null;
        
        $stmt = $this->pdo->prepare("
            SELECT factor_id FROM trip_external_factor WHERE trip_id=?
        ");
        $stmt->execute([$id]);
        $row['external_factors'] = array_column($stmt->fetchAll(), 'factor_id');
        
        return DrivingExperience::fromDatabaseRow($row);
    }
}
